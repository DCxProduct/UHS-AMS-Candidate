<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class CandidateNumberStorageMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_is_repeatable_and_preserves_existing_numbers_data_and_sequence_values(): void
    {
        $migration = $this->migration();
        $migration->down();
        $form = $this->form();
        $user = $this->user('legacy_number');
        $entry = DB::table('custom_form_entries')->insertGetId([
            'custom_form_id' => $form, 'created_by' => $user, 'candidate_id' => 'BNM260037',
            'review_status' => 'approved', 'data' => json_encode(['major' => 'bnm', 'custom_value' => 'preserve me']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->account($form, $user, 26, 'BNM260037');
        $this->sequence($form, 26, 37);
        $entryBefore = (array) DB::table('custom_form_entries')->find($entry);
        $accountsBefore = DB::table('candidate_account_ids')->get()->toArray();
        $sequencesBefore = DB::table('candidate_id_sequences')->get()->toArray();

        $migration->up();
        $migration->up();

        $entryAfter = (array) DB::table('custom_form_entries')->find($entry);
        $this->assertArrayHasKey('candidate_id_year', $entryAfter);
        $this->assertNull($entryAfter['candidate_id_year']);
        unset($entryAfter['candidate_id_year']);
        $this->assertSame($entryBefore, $entryAfter);
        $this->assertEquals($accountsBefore, DB::table('candidate_account_ids')->get()->toArray());
        $this->assertEquals($sequencesBefore, DB::table('candidate_id_sequences')->get()->toArray());
        $this->assertFalse($this->hasIndex('candidate_account_ids', 'candidate_form_id_unique'));
        $this->assertSame(['custom_form_id', 'year_suffix', 'candidate_id'], $this->indexColumns('candidate_account_ids', 'candidate_form_year_id_unique'));
        $this->assertSame(['custom_form_id', 'user_id', 'major_abbreviation', 'year_suffix'], $this->indexColumns('candidate_account_ids', 'candidate_form_account_major_year_unique'));
        $this->assertSame(['custom_form_id', 'major_abbreviation', 'submission_year'], $this->indexColumns('candidate_id_sequences', 'candidate_form_major_year_sequence_unique'));
    }

    public function test_equal_yearless_numbers_are_allowed_across_years_but_not_within_one_form_year(): void
    {
        $form = $this->form();
        $this->account($form, $this->user('year_26'), 26, 'BNM-0001');
        $this->account($form, $this->user('year_27'), 27, 'BNM-0001');
        $this->assertDatabaseCount('candidate_account_ids', 2);

        $this->expectException(QueryException::class);
        $this->account($form, $this->user('same_year_collision'), 26, 'BNM-0001');
    }

    public function test_down_refuses_cross_year_collisions_before_changing_any_schema_or_data(): void
    {
        $form = $this->form();
        $this->account($form, $this->user('rollback_26'), 26, 'BNM-0001');
        $this->account($form, $this->user('rollback_27'), 27, 'BNM-0001');
        $before = DB::table('candidate_account_ids')->get()->toArray();

        try {
            $this->migration()->down();
            $this->fail('Rollback must not remove constraints or renumber historical candidates.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Cannot roll back yearless candidate numbers', $exception->getMessage());
            $this->assertStringContainsString('No schema or data has been changed', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('custom_form_entries', 'candidate_id_year'));
        $this->assertTrue($this->hasIndex('candidate_account_ids', 'candidate_form_year_id_unique'));
        $this->assertFalse($this->hasIndex('candidate_account_ids', 'candidate_form_id_unique'));
        $this->assertEquals($before, DB::table('candidate_account_ids')->get()->toArray());
    }

    public function test_down_restores_the_old_constraint_without_resetting_numbers_or_sequences_when_safe(): void
    {
        $form = $this->form();
        $this->account($form, $this->user('safe_rollback'), 26, 'BNM-0001');
        $this->sequence($form, 26, 19);
        $accountsBefore = DB::table('candidate_account_ids')->get()->toArray();
        $sequencesBefore = DB::table('candidate_id_sequences')->get()->toArray();

        $this->migration()->down();

        $this->assertFalse(Schema::hasColumn('custom_form_entries', 'candidate_id_year'));
        $this->assertSame(['custom_form_id', 'candidate_id'], $this->indexColumns('candidate_account_ids', 'candidate_form_id_unique'));
        $this->assertFalse($this->hasIndex('candidate_account_ids', 'candidate_form_year_id_unique'));
        $this->assertEquals($accountsBefore, DB::table('candidate_account_ids')->get()->toArray());
        $this->assertEquals($sequencesBefore, DB::table('candidate_id_sequences')->get()->toArray());
        $this->expectException(QueryException::class);
        $this->account($form, $this->user('old_constraint_collision'), 27, 'BNM-0001');
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_30_000001_support_yearless_candidate_numbers.php');
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $index): bool => $index['name'] === $name);
    }

    private function indexColumns(string $table, string $name): array
    {
        $index = collect(Schema::getIndexes($table))->first(fn (array $index): bool => $index['name'] === $name);
        $this->assertNotNull($index);
        $this->assertTrue($index['unique']);

        return $index['columns'];
    }

    private function form(): int
    {
        return DB::table('custom_forms')->insertGetId([
            'name' => 'Yearless storage form', 'slug' => 'yearless-storage-form', 'schema' => json_encode([]),
            'is_active' => true, 'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function user(string $username): int
    {
        return DB::table('users')->insertGetId([
            'name' => $username, 'username' => $username, 'date_of_birth' => '2000-01-01',
            'password' => bcrypt('password'), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function account(int $form, int $user, int $year, string $number): void
    {
        DB::table('candidate_account_ids')->insert([
            'custom_form_id' => $form, 'user_id' => $user, 'major_abbreviation' => 'BNM',
            'year_suffix' => $year, 'candidate_id' => $number, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function sequence(int $form, int $year, int $value): void
    {
        DB::table('candidate_id_sequences')->insert([
            'custom_form_id' => $form, 'major_abbreviation' => 'BNM', 'submission_year' => $year,
            'last_sequence' => $value, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
