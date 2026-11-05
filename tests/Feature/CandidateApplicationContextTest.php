<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CandidateEntranceStatistics\Tables\CandidateEntranceStatisticsTable;
use App\Filament\Admin\Resources\ExamResults\Tables\ExamResultsTable;
use App\Models\Role;
use App\Models\User;
use App\Models\UserType;
use App\Support\CandidateApplicationContext;
use App\Support\CandidateStatisticsSynchronizer;
use App\Support\CandidateTypeResolver;
use App\Support\StatisticsMenuOptions;
use App\Support\UserTypeOptions;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use ReflectionMethod;
use Tests\TestCase;

class CandidateApplicationContextTest extends TestCase
{
    use RefreshDatabase;

    private const BACHELOR = 'national_entrance_exam_application_bachelor';

    private const ASSOCIATE = 'national_entrance_exam_application_associate';

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        Notification::fake();
        Queue::fake();
        UserTypeOptions::options();
    }

    public function test_one_owner_has_distinct_trusted_types_and_isolated_statistics_filters_and_exports(): void
    {
        $owner = $this->owner();
        $bachelor = $this->entry($owner, $this->form(self::BACHELOR), ['answer' => 'Bachelor answer']);
        $associate = $this->entry($owner, $this->form(self::ASSOCIATE, [self::BACHELOR]), [
            'answer' => 'Associate answer',
            'application_candidate_type' => self::BACHELOR,
            'user_type' => self::BACHELOR,
        ]);

        $this->assertSame(self::BACHELOR, CandidateTypeResolver::resolve($owner, ['user_type' => self::ASSOCIATE]));
        $this->assertSame(self::BACHELOR, CandidateTypeResolver::forEntry($bachelor));
        $this->assertSame(self::ASSOCIATE, CandidateTypeResolver::forEntry($associate));
        $this->assertSame('Associate answer', $associate->data['answer']);
        $this->assertSame(self::ASSOCIATE, $associate->data[CandidateApplicationContext::DATA_KEY]);

        $filter = new ReflectionMethod(CandidateEntranceStatisticsTable::class, 'applyCandidateTypeFilter');
        $this->assertSame([$bachelor->id], $filter->invoke(null, CustomFormEntry::query(), self::BACHELOR)->pluck('id')->all());
        $this->assertSame([$associate->id], $filter->invoke(null, CustomFormEntry::query(), self::ASSOCIATE)->pluck('id')->all());
        $this->assertSame([], $filter->invoke(null, CustomFormEntry::query()->whereKey($bachelor->id), self::ASSOCIATE)->pluck('id')->all());

        $definitions = (new ReflectionMethod(CandidateEntranceStatisticsTable::class, 'exportColumnDefinitions'))->invoke(null);
        $this->assertSame(self::ASSOCIATE, $definitions['user_type']['clean']($associate));
        $this->assertSame(UserTypeOptions::formatLabel(self::ASSOCIATE), $definitions['user_type']['value']($associate));
        $degree = new ReflectionMethod(ExamResultsTable::class, 'degreeLevelKey');
        $this->assertSame('bachelor', $degree->invoke(null, $bachelor));
        $this->assertSame('associate', $degree->invoke(null, $associate));
        $degreeFilter = new ReflectionMethod(ExamResultsTable::class, 'applyDegreeLevelFilter');
        $this->assertSame([$associate->id], $degreeFilter->invoke(null, CustomFormEntry::query(), 'associate')->pluck('id')->all());
    }

    public function test_saved_context_is_immutable_even_when_form_account_and_posted_metadata_change(): void
    {
        $owner = $this->owner();
        $form = $this->form(self::ASSOCIATE);
        $entry = $this->entry($owner, $form);
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $form->update(['slug' => 'renamed-form', 'allowed_roles' => [self::BACHELOR]]);
        $owner->syncRoles([Role::findOrCreate('candidate', 'web')]);

        $entry->data = ['answer' => 'Edited', 'application_candidate_type' => self::BACHELOR];
        $entry->save();

        $this->assertSame(self::ASSOCIATE, $entry->fresh()->data[CandidateApplicationContext::DATA_KEY]);
        $this->assertSame(self::ASSOCIATE, CandidateTypeResolver::forEntry($entry->fresh()));
        $this->assertSame($snapshot, $entry->fresh()->getRawOriginal('workflow_snapshot'));

        $partial = new CustomFormEntry;
        $partial->setRawAttributes(['id' => $entry->id], true);
        $partial->exists = true;
        $partial->data = ['answer' => 'Partial edit', 'application_candidate_type' => self::BACHELOR];
        $partial->save();
        $this->assertSame(self::ASSOCIATE, $entry->fresh()->data[CandidateApplicationContext::DATA_KEY]);

        $form->update(['slug' => 'profile']);
        $entry->refresh();
        $entry->data = ['application_candidate_type' => self::BACHELOR];
        $entry->save();
        $this->assertSame(self::ASSOCIATE, CandidateTypeResolver::forEntry($entry->fresh()));
    }

    public function test_legacy_entries_resolve_per_form_without_backfilling_or_accepting_a_forged_stamp(): void
    {
        $owner = $this->owner();
        $associate = $this->legacyEntry($owner, $this->form(self::ASSOCIATE), ['answer' => 'Original']);
        $fallback = $this->legacyEntry($owner, $this->form('generic-legacy-form', []), ['user_type' => self::ASSOCIATE]);

        $this->assertSame(self::ASSOCIATE, CandidateTypeResolver::forEntry($associate));
        $this->assertSame(self::BACHELOR, CandidateTypeResolver::forEntry($fallback));
        $this->assertArrayNotHasKey(CandidateApplicationContext::DATA_KEY, $associate->fresh()->data);
        $this->assertSame([$associate->id], CandidateTypeResolver::filterEntries(CustomFormEntry::query(), self::ASSOCIATE)->pluck('id')->all());
        $this->assertSame([$fallback->id], CandidateTypeResolver::filterEntries(CustomFormEntry::query(), self::BACHELOR)->pluck('id')->all());

        $associate->data = ['answer' => 'Edited', 'application_candidate_type' => self::BACHELOR];
        $associate->save();
        $this->assertArrayNotHasKey(CandidateApplicationContext::DATA_KEY, $associate->fresh()->data);
        $this->assertSame(self::ASSOCIATE, CandidateTypeResolver::forEntry($associate->fresh()));
    }

    public function test_context_uses_unique_form_type_or_current_type_and_removes_untrusted_payload_values(): void
    {
        $owner = $this->owner();
        $form = $this->form('combined-education-form', [self::BACHELOR, self::ASSOCIATE]);
        $this->assertSame(self::BACHELOR, CandidateApplicationContext::candidateType($form, $owner));
        $this->assertSame(self::BACHELOR, CandidateApplicationContext::dataForForm($form, $owner, [
            'application_candidate_type' => self::ASSOCIATE,
        ])[CandidateApplicationContext::DATA_KEY]);

        $unique = $this->form('unique-associate-form', [self::ASSOCIATE]);
        $this->assertSame(self::ASSOCIATE, CandidateApplicationContext::candidateType($unique));
    }

    public function test_profile_is_not_stamped_and_saved_type_survives_catalog_removal(): void
    {
        $owner = $this->owner();
        $profile = $this->entry($owner, $this->form('profile', [self::ASSOCIATE]), [
            'personal' => 'Shared', 'application_candidate_type' => self::ASSOCIATE,
        ]);
        $this->assertArrayNotHasKey(CandidateApplicationContext::DATA_KEY, $profile->data);
        $entry = $this->entry($owner, $this->form(self::ASSOCIATE));
        UserType::query()->where('key', self::ASSOCIATE)->firstOrFail()->delete();
        $this->assertSame(self::ASSOCIATE, CandidateTypeResolver::forEntry($entry->fresh()));
    }

    public function test_exit_statistics_are_stored_per_entry_with_their_application_context(): void
    {
        $owner = $this->owner();
        $bachelorForm = $this->form(self::BACHELOR);
        $associateForm = $this->form(self::ASSOCIATE);
        $bachelorForm->update(['statistics_menu' => StatisticsMenuOptions::EXIT_EXAM_STATISTICS]);
        $associateForm->update(['statistics_menu' => StatisticsMenuOptions::EXIT_EXAM_STATISTICS]);
        $bachelor = $this->entry($owner, $bachelorForm, ['candidate_status' => 'pending']);
        $associate = $this->entry($owner, $associateForm, ['candidate_status' => 'passed']);

        CandidateStatisticsSynchronizer::syncEntry($bachelor);
        CandidateStatisticsSynchronizer::syncEntry($associate);

        $this->assertDatabaseHas('candidate_exit_statistics', ['custom_form_entry_id' => $bachelor->id, 'user_type' => self::BACHELOR, 'candidate_status' => 'pending']);
        $this->assertDatabaseHas('candidate_exit_statistics', ['custom_form_entry_id' => $associate->id, 'user_type' => self::ASSOCIATE, 'candidate_status' => 'passed']);
        $this->assertDatabaseCount('candidate_exit_statistics', 2);
    }

    private function owner(): User
    {
        $user = User::query()->create([
            'registration_type' => 'student', 'name' => 'Shared candidate', 'username' => 'shared-candidate',
            'email' => 'shared-candidate@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true,
        ]);
        $user->assignRole(Role::findOrCreate(self::BACHELOR, 'web'));

        return $user;
    }

    private function form(string $typeOrSlug, ?array $roles = null): CustomForm
    {
        return CustomForm::query()->create([
            'name' => $typeOrSlug, 'slug' => str_replace('_', '-', $typeOrSlug), 'is_active' => true,
            'allowed_roles' => $roles ?? [$typeOrSlug], 'requires_payment' => false,
        ]);
    }

    private function entry(User $owner, CustomForm $form, array $data = []): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->id, 'created_by' => $owner->id, 'review_status' => 'pending', 'data' => $data,
        ]);
    }

    private function legacyEntry(User $owner, CustomForm $form, array $data): CustomFormEntry
    {
        $id = DB::table('custom_form_entries')->insertGetId([
            'custom_form_id' => $form->id, 'created_by' => $owner->id, 'review_status' => 'pending',
            'data' => json_encode($data), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return CustomFormEntry::query()->findOrFail($id);
    }
}
