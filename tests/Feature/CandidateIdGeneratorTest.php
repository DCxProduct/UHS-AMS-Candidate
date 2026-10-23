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

        $this->assertSame('BNM260001', $generator->next($bnm->getKey(), 2026, $formId));
        $this->assertSame('BNM260002', $generator->next($bnm->getKey(), 2026, $formId));
        $this->assertSame('BBN260001', $generator->next($bbn->getKey(), 2026, $formId));
        $this->assertSame('BBN260002', $generator->next($bbn->getKey(), 2026, $formId));
        $this->assertSame('BNM260003', $generator->next($bnm->getKey(), 2026, $formId));
        $this->assertSame('BNM270001', $generator->next($bnm->getKey(), 2027, $formId));
        $this->assertSame('BNM260001', $generator->next($bnm->getKey(), 2026, $otherFormId));
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

        $this->assertSame('BBN260001', app(CandidateIdGenerator::class)->next('BBN-Bachelor Degree of Science in Nursing', 2026, $formId));
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
        $this->assertSame('BNM'.now()->format('y').'0001', $generator->assignToEntry($entry));
        $this->assertSame('BNM'.now()->format('y').'0001', $generator->assignToEntry($entry));
        $this->assertSame('BBN'.now()->format('y').'0001', $generator->assignToEntry($otherEntry));
        $this->assertSame('BNM'.now()->format('y').'0001', $generator->assignToEntry($sameMajorEntry));
        $this->assertDatabaseHas('custom_form_entries', [
            'id' => $otherEntryId,
            'candidate_id' => 'BBN'.now()->format('y').'0001',
        ]);
        $this->assertDatabaseHas('custom_form_entries', [
            'id' => $sameMajorEntryId,
            'candidate_id' => 'BNM'.now()->format('y').'0001',
        ]);
        $this->assertDatabaseHas('candidate_account_ids', [
            'custom_form_id' => $formId,
            'user_id' => $userId,
            'major_abbreviation' => 'BBN',
            'year_suffix' => (int) now()->format('y'),
            'candidate_id' => 'BBN'.now()->format('y').'0001',
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

        $this->assertSame('BNM'.now()->format('y').'0001', $generator->assignToEntry(CustomFormEntry::query()->findOrFail($entryId)));
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
        $this->assertSame('BNM'.now()->format('y').'0001', $generator->assignToEntry(CustomFormEntry::query()->findOrFail($newEntryId)));
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
