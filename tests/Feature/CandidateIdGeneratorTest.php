<?php

namespace Tests\Feature;

use App\Models\Major;
use App\Services\CandidateIdGenerator;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CandidateIdGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_ids_sequence_by_form_major_and_submission_year(): void
    {
        $bnm = Major::query()->create([
            'key' => 'nurse_midwifery',
            'abbreviation' => 'BNM',
            'name_en' => 'Bachelor Degree in Nurse-Midwifery',
            'is_active' => true,
        ]);
        $bbn = Major::query()->create([
            'key' => 'nursing',
            'abbreviation' => 'BBN',
            'name_en' => 'Bachelor Degree of Science in Nursing',
            'is_active' => true,
        ]);

        $generator = app(CandidateIdGenerator::class);
        $formId = $this->createForm('sequence-form');
        $otherFormId = $this->createForm('other-sequence-form');

        $this->assertSame('BNM-0001', $generator->next($bnm->getKey(), 2026, $formId));
        $this->assertSame('BNM-0002', $generator->next($bnm->getKey(), 2026, $formId));
        $this->assertSame('BBN-0001', $generator->next($bbn->getKey(), 2026, $formId));
        $this->assertSame('BBN-0002', $generator->next($bbn->getKey(), 2026, $formId));
        $this->assertSame('BNM-0003', $generator->next($bnm->getKey(), 2026, $formId));
        $this->assertSame('BNM-0001', $generator->next($bnm->getKey(), 2027, $formId));
        $this->assertSame('BNM-0001', $generator->next($bnm->getKey(), 2026, $otherFormId));
    }

    public function test_candidate_id_generator_accepts_abbreviation_prefixed_major_label(): void
    {
        Major::query()->create([
            'key' => 'nursing',
            'abbreviation' => 'BBN',
            'name_en' => 'Bachelor Degree of Science in Nursing',
            'is_active' => true,
        ]);

        $formId = $this->createForm('prefixed-major-form');

        $this->assertSame('BBN-0001', app(CandidateIdGenerator::class)->next('BBN-Bachelor Degree of Science in Nursing', 2026, $formId));
    }

    public function test_candidate_id_is_assigned_on_check_and_reused_per_account_major_and_year(): void
    {
        $major = Major::query()->create([
            'key' => 'nurse_midwifery',
            'abbreviation' => 'BNM',
            'name_en' => 'Bachelor Degree in Nurse-Midwifery',
            'is_active' => true,
        ]);
        $otherMajor = Major::query()->create([
            'key' => 'nursing',
            'abbreviation' => 'BBN',
            'name_en' => 'Bachelor Degree of Science in Nursing',
            'is_active' => true,
        ]);
        $now = now();
        $userId = DB::table('users')->insertGetId([
            'name' => 'Candidate Test',
            'username' => 'candidate-id-test-'.uniqid(),
            'date_of_birth' => '2000-01-01',
            'password' => bcrypt('password'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $formId = DB::table('custom_forms')->insertGetId([
            'name' => 'Candidate application',
            'slug' => 'candidate-application-test',
            'schema' => json_encode([]),
            'is_active' => true,
            'display_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $entryId = DB::table('custom_form_entries')->insertGetId([
            'custom_form_id' => $formId,
            'data' => json_encode(['major' => (string) $major->getKey()]),
            'created_by' => $userId,
            'review_status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $otherEntryId = DB::table('custom_form_entries')->insertGetId([
            'custom_form_id' => $formId,
            'data' => json_encode(['major' => (string) $otherMajor->getKey()]),
            'created_by' => $userId,
            'review_status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sameMajorEntryId = DB::table('custom_form_entries')->insertGetId([
            'custom_form_id' => $formId,
            'data' => json_encode(['major' => (string) $major->getKey()]),
            'created_by' => $userId,
            'review_status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $entry = CustomFormEntry::query()->findOrFail($entryId);
        $otherEntry = CustomFormEntry::query()->findOrFail($otherEntryId);
        $sameMajorEntry = CustomFormEntry::query()->findOrFail($sameMajorEntryId);
        $generator = app(CandidateIdGenerator::class);

        $this->assertDatabaseHas('custom_form_entries', ['id' => $entryId, 'candidate_id' => null]);
        $this->assertSame('BNM-0001', $generator->assignToEntry($entry));
        $this->assertSame('BNM-0001', $generator->assignToEntry($entry));
        $this->assertSame('BBN-0001', $generator->assignToEntry($otherEntry));
        $this->assertSame('BNM-0001', $generator->assignToEntry($sameMajorEntry));
        $this->assertDatabaseHas('custom_form_entries', [
            'id' => $otherEntryId,
            'candidate_id' => 'BBN-0001',
            'candidate_id_year' => now()->year,
        ]);
        $this->assertDatabaseHas('custom_form_entries', [
            'id' => $sameMajorEntryId,
            'candidate_id' => 'BNM-0001',
            'candidate_id_year' => now()->year,
        ]);
        $this->assertDatabaseHas('candidate_account_ids', [
            'custom_form_id' => $formId,
            'user_id' => $userId,
            'major_abbreviation' => 'BBN',
            'year_suffix' => (int) now()->format('y'),
            'candidate_id' => 'BBN-0001',
        ]);

        $this->assertDatabaseHas('candidate_id_sequences', [
            'custom_form_id' => $formId, 'major_abbreviation' => 'BNM',
            'submission_year' => (int) now()->format('y'), 'last_sequence' => 1,
        ]);
    }

    public function test_clearing_an_empty_form_resets_its_candidate_id_sequence(): void
    {
        $major = Major::query()->create([
            'key' => 'nurse_midwifery',
            'abbreviation' => 'BNM',
            'name_en' => 'Bachelor Degree in Nurse-Midwifery',
            'is_active' => true,
        ]);
        $formId = $this->createForm('clear-sequence-form');
        $generator = app(CandidateIdGenerator::class);
        $now = now();
        $userId = DB::table('users')->insertGetId([
            'name' => 'Clear sequence test',
            'username' => 'clear-sequence-test-'.uniqid(),
            'date_of_birth' => '2000-01-01',
            'password' => bcrypt('password'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $entryId = DB::table('custom_form_entries')->insertGetId([
            'custom_form_id' => $formId,
            'data' => json_encode(['major' => (string) $major->getKey()]),
            'created_by' => $userId,
            'review_status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->assertSame('BNM-0001', $generator->assignToEntry(CustomFormEntry::query()->findOrFail($entryId)));
        DB::table('custom_form_entries')->where('custom_form_id', $formId)->delete();
        $generator->resetFormSequenceIfEmpty($formId);

        $newEntryId = DB::table('custom_form_entries')->insertGetId([
            'custom_form_id' => $formId,
            'data' => json_encode(['major' => (string) $major->getKey()]),
            'created_by' => $userId,
            'review_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertSame('BNM-0001', $generator->assignToEntry(CustomFormEntry::query()->findOrFail($newEntryId)));
    }

    public function test_the_new_format_continues_the_existing_major_year_sequence(): void
    {
        $major = $this->createMajor();
        $form = $this->createForm('continued-sequence');
        DB::table('candidate_id_sequences')->insert([
            'custom_form_id' => $form, 'major_abbreviation' => 'BNM',
            'submission_year' => 26, 'last_sequence' => 41,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame('BNM-0042', app(CandidateIdGenerator::class)->next($major->id, 2026, $form));
        $this->assertDatabaseHas('candidate_id_sequences', [
            'custom_form_id' => $form, 'major_abbreviation' => 'BNM',
            'submission_year' => 26, 'last_sequence' => 42,
        ]);
    }

    public function test_an_existing_account_mapping_keeps_its_legacy_id_without_consuming_a_sequence(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 30));
        $major = $this->createMajor();
        $form = $this->createForm('legacy-account-mapping');
        $user = $this->createUser();
        $entry = $this->createEntry($form, $major->id, $user);
        DB::table('candidate_account_ids')->insert([
            'custom_form_id' => $form, 'user_id' => $user,
            'major_abbreviation' => 'BNM', 'year_suffix' => 26, 'candidate_id' => 'BNM260017',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame('BNM260017', app(CandidateIdGenerator::class)->assignToEntry($entry));
        $this->assertDatabaseHas('custom_form_entries', [
            'id' => $entry->id, 'candidate_id' => 'BNM260017', 'candidate_id_year' => 2026,
        ]);
        $this->assertDatabaseCount('candidate_id_sequences', 0);
    }

    public function test_a_legacy_entry_keeps_its_id_when_its_account_mapping_is_missing(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 30));
        $major = $this->createMajor();
        $form = $this->createForm('legacy-entry-mapping');
        $user = $this->createUser();
        $entry = $this->createEntry($form, $major->id, $user, ['candidate_id' => 'BNM260021']);

        $this->assertSame('BNM260021', app(CandidateIdGenerator::class)->assignToEntry($entry));
        $this->assertDatabaseHas('candidate_account_ids', [
            'custom_form_id' => $form, 'user_id' => $user, 'year_suffix' => 26,
            'candidate_id' => 'BNM260021',
        ]);
        $this->assertDatabaseHas('custom_form_entries', [
            'id' => $entry->id, 'candidate_id' => 'BNM260021', 'candidate_id_year' => 2026,
        ]);
        $this->assertDatabaseCount('candidate_id_sequences', 0);
    }

    public function test_different_years_keep_separate_account_ids_even_when_the_formatted_number_is_the_same(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 30));
        $major = $this->createMajor();
        $form = $this->createForm('account-year-reset');
        $user = $this->createUser();
        $generator = app(CandidateIdGenerator::class);
        $first = $this->createEntry($form, $major->id, $user);
        $this->assertSame('BNM-0001', $generator->assignToEntry($first));

        $this->travelTo(now()->setDate(2027, 10, 30));
        $second = $this->createEntry($form, $major->id, $user);
        $this->assertSame('BNM-0001', $generator->assignToEntry($second));
        $this->assertSame('BNM-0001', $generator->assignToEntry($second));

        foreach ([26, 27] as $yearSuffix) {
            $this->assertDatabaseHas('candidate_account_ids', [
                'custom_form_id' => $form, 'user_id' => $user,
                'major_abbreviation' => 'BNM', 'year_suffix' => $yearSuffix,
                'candidate_id' => 'BNM-0001',
            ]);
            $this->assertDatabaseHas('candidate_id_sequences', [
                'custom_form_id' => $form, 'major_abbreviation' => 'BNM',
                'submission_year' => $yearSuffix, 'last_sequence' => 1,
            ]);
        }

        $this->assertDatabaseHas('custom_form_entries', ['id' => $first->id, 'candidate_id_year' => 2026]);
        $this->assertDatabaseHas('custom_form_entries', ['id' => $second->id, 'candidate_id_year' => 2027]);
    }

    public function test_ownerless_checks_use_durable_year_metadata_for_reuse_and_year_resets(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 30));
        $major = $this->createMajor();
        $form = $this->createForm('ownerless-year-reset');
        $entry = $this->createEntry($form, $major->id);
        $generator = app(CandidateIdGenerator::class);

        $this->assertSame('BNM-0001', $generator->assignToEntry($entry));
        $this->assertDatabaseHas('custom_form_entries', [
            'id' => $entry->id, 'candidate_id' => 'BNM-0001', 'candidate_id_year' => 2026,
        ]);
        DB::table('custom_form_entries')->where('id', $entry->id)->update([
            'updated_at' => '2035-01-01 00:00:00', 'reviewed_at' => '2035-01-01 00:00:00',
        ]);
        $this->assertSame('BNM-0001', $generator->assignToEntry($entry));
        $this->assertDatabaseHas('candidate_id_sequences', [
            'custom_form_id' => $form, 'submission_year' => 26, 'last_sequence' => 1,
        ]);

        $this->travelTo(now()->setDate(2027, 10, 30));
        $this->assertSame('BNM-0001', $generator->assignToEntry($entry));
        $this->assertSame('BNM-0001', $generator->assignToEntry($entry));
        $this->assertDatabaseHas('custom_form_entries', [
            'id' => $entry->id, 'candidate_id' => 'BNM-0001', 'candidate_id_year' => 2027,
        ]);
        $this->assertDatabaseHas('candidate_id_sequences', [
            'custom_form_id' => $form, 'submission_year' => 27, 'last_sequence' => 1,
        ]);
    }

    public function test_ownerless_legacy_ids_remain_idempotent_without_year_metadata(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 30));
        $major = $this->createMajor();
        $form = $this->createForm('ownerless-legacy-id');
        $entry = $this->createEntry($form, $major->id, null, ['candidate_id' => 'BNM260041']);

        $this->assertSame('BNM260041', app(CandidateIdGenerator::class)->assignToEntry($entry));
        $this->assertDatabaseHas('custom_form_entries', [
            'id' => $entry->id, 'candidate_id' => 'BNM260041', 'candidate_id_year' => 2026,
        ]);
        $this->assertDatabaseCount('candidate_id_sequences', 0);
    }

    private function createMajor(): Major
    {
        return Major::query()->create([
            'key' => 'bnm', 'abbreviation' => 'BNM', 'name_en' => 'Nurse Midwifery', 'is_active' => true,
        ]);
    }

    private function createUser(): int
    {
        return DB::table('users')->insertGetId([
            'name' => 'Generator candidate', 'username' => 'generator-'.uniqid(),
            'date_of_birth' => '2000-01-01', 'password' => bcrypt('password'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function createEntry(int $form, int $major, ?int $user = null, array $attributes = []): CustomFormEntry
    {
        $id = DB::table('custom_form_entries')->insertGetId([
            'custom_form_id' => $form, 'created_by' => $user,
            'data' => json_encode(['major' => (string) $major]), 'review_status' => 'pending',
            'created_at' => now(), 'updated_at' => now(), ...$attributes,
        ]);

        return CustomFormEntry::query()->findOrFail($id);
    }

    private function createForm(string $slug): int
    {
        $now = now();

        return (int) DB::table('custom_forms')->insertGetId([
            'name' => $slug,
            'slug' => $slug,
            'schema' => json_encode([]),
            'is_active' => true,
            'display_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
