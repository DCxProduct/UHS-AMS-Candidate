<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\WorkflowMessagePlaceholders;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowSubmissionMessages;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowStaffMessagePlaceholdersTest extends TestCase
{
    use RefreshDatabase;

    private const BODY = ':candidate | :canddiate | :form | :candidate_id | :id | :form_id';

    private const CANDIDATE_BODY = 'Candidate message: :candidate | :form | :candidate_id | :id.';

    private User $candidate;

    private User $englishStaff;

    private User $khmerStaff;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        Role::findOrCreate('admin', 'web');
        $this->candidate = $this->user('student', 'en');
        $this->candidate->forceFill([
            'name' => 'Owner fallback name', 'first_name_kh' => 'ម្ចាស់', 'last_name_kh' => 'គណនី',
            'name_latin' => 'OWNER FALLBACK NAME',
        ])->saveQuietly();
        $this->englishStaff = $this->user('admin', 'en');
        $this->englishStaff->assignRole('admin');
        $this->khmerStaff = $this->user('admin', 'km');
        $this->khmerStaff->assignRole('admin');
        $this->actingAs($this->englishStaff);
    }

    public static function staffEvents(): array
    {
        return [
            ['form_submission', null], ['review', 'accept'], ['review', 'send_back'], ['review', 'reject'],
            ['payment', null], ['awaiting_results', null], ['completed', null],
        ];
    }

    #[DataProvider('staffEvents')]
    public function test_frozen_staff_and_candidate_messages_render_placeholders_in_the_recipients_language(string $type, ?string $action): void
    {
        $unselected = $this->user('admin', 'en');
        $inactive = $this->user('admin', 'km');
        $inactive->assignRole('admin');
        $inactive->forceFill(['is_active' => false])->saveQuietly();
        [$form, $workflow] = $this->workflow($type);
        $entry = $this->entry($form);
        $frozen = $entry->workflow_snapshot;
        $stages = $workflow->stages;
        if ($type === 'review') {
            $stages[0]['data']['review_actions'][$action]['staff_notification_message'] = 'Changed after application started';
        } elseif ($type === 'form_submission') {
            $stages[0]['data']['submission_messages']['staff_notification_message'] = 'Changed after application started';
        } else {
            $stages[0]['data']['audience_messages']['staff_notification_message'] = 'Changed after application started';
        }
        $workflow->update(['stages' => $stages]);

        $this->deliver($entry, $type, $action);
        $event = $type === 'review' ? 'review_action_staff:'.$action : ($type === 'form_submission' ? 'submission_staff' : 'stage_staff:'.$type);
        $english = $this->englishStaff->notifications()->where('data->viewData->workflow_event', $event)->sole();
        $khmer = $this->khmerStaff->notifications()->where('data->viewData->workflow_event', $event)->sole();

        $this->assertSame('SOK DARA | SOK DARA | Entrance application |  |  | :form_id', $english->data['body']);
        $this->assertSame('សុខ ដារ៉ា | សុខ ដារ៉ា | ពាក្យស្នើសុំប្រឡងចូល |  |  | :form_id', $khmer->data['body']);
        $this->assertSame((string) $entry->id, data_get($english->data, 'viewData.workflow_entry_id'));
        $this->assertSame((string) $entry->id, data_get($khmer->data, 'viewData.workflow_entry_id'));
        $this->assertSame($frozen, $entry->fresh()->workflow_snapshot);
        $this->assertSame(0, $unselected->notifications()->count());
        $this->assertSame(0, $inactive->notifications()->count());
        $this->assertSame('Candidate message: SOK DARA | Entrance application |  | .', $this->candidate->notifications()->sole()->data['body']);
        $this->assertSame('en', app()->getLocale());

        $this->deliver($entry, $type, $action);
        $expected = in_array($action, ['send_back', 'reject'], true) ? 2 : 1;
        $this->assertSame($expected, $this->englishStaff->notifications()->where('data->viewData->workflow_event', $event)->count());
        $this->assertSame($expected, $this->khmerStaff->notifications()->where('data->viewData->workflow_event', $event)->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_name_resolution_prefers_entry_payload_and_falls_back_to_candidate_owner_without_modifying_data(): void
    {
        [$form] = $this->workflow('review');
        $entry = $this->entry($form);
        $before = $entry->getAttributes();
        $this->assertSame('SOK DARA Entrance application', WorkflowMessagePlaceholders::render(':candidate :form', $entry));
        app()->setLocale('km');
        $this->assertSame('សុខ ដារ៉ា ពាក្យស្នើសុំប្រឡងចូល', WorkflowMessagePlaceholders::render(':canddiate :form', $entry));
        $this->assertSame($before, $entry->fresh()->getAttributes());

        $ownerOnly = CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->id, 'created_by' => $this->candidate->id,
            'review_status' => 'pending', 'data' => ['registration_status' => 'pending'],
        ]);
        $this->assertSame('ម្ចាស់ គណនី', WorkflowMessagePlaceholders::render(':candidate', $ownerOnly));
        app()->setLocale('en');
        $this->assertSame('OWNER FALLBACK NAME', WorkflowMessagePlaceholders::render(':candidate', $ownerOnly));
        $this->assertSame('Keep  :form_id :unknown unchanged', WorkflowMessagePlaceholders::render('Keep :candidate_id :form_id :unknown unchanged', $entry));
    }

    public function test_candidate_id_tokens_use_only_the_issued_entry_id_and_respect_token_boundaries(): void
    {
        [$form] = $this->workflow('review');
        $entry = $this->entry($form);
        $entry->forceFill(['candidate_id' => 'BNM-0042', 'candidate_id_year' => 2026])->saveQuietly();
        $entry->refresh();
        $before = $entry->getRawOriginal();
        $message = 'ID(:candidate_id), alias[:id]. Keep :candidate_id_year :identity :id_suffix :id2 :candidate_id_extra :form_id.';

        $this->assertSame('ID(BNM-0042), alias[BNM-0042]. Keep :candidate_id_year :identity :id_suffix :id2 :candidate_id_extra :form_id.', WorkflowMessagePlaceholders::render($message, $entry));

        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertDatabaseCount('candidate_id_sequences', 0);
        $this->assertDatabaseCount('candidate_account_ids', 0);
    }

    public function test_missing_id_tokens_do_not_use_the_entry_key_payload_account_mapping_or_sibling_id(): void
    {
        [$form] = $this->workflow('review');
        $entry = $this->entry($form);
        $entry->forceFill(['data' => [...$entry->data, 'candidate_id' => 'PAYLOAD-ID-MUST-NOT-BE-USED']])->saveQuietly();
        $sibling = $this->entry($form);
        $sibling->forceFill(['candidate_id' => 'SIBLING-ID-MUST-NOT-BE-USED'])->saveQuietly();
        DB::table('candidate_account_ids')->insert([
            'custom_form_id' => $form->id, 'user_id' => $this->candidate->id, 'major_abbreviation' => 'BNM',
            'year_suffix' => (int) now()->format('y'), 'candidate_id' => 'ACCOUNT-ID-MUST-NOT-BE-USED',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $entry->refresh();
        $before = $entry->getRawOriginal();
        $siblingBefore = $sibling->fresh()->getRawOriginal();
        $mappingBefore = (array) DB::table('candidate_account_ids')->sole();

        $this->assertSame('Canonical[] Alias[]', WorkflowMessagePlaceholders::render('Canonical[:candidate_id] Alias[:id]', $entry));

        $this->assertGreaterThan(0, $entry->id);
        $this->assertNull($entry->candidate_id);
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertSame($siblingBefore, $sibling->fresh()->getRawOriginal());
        $this->assertSame($mappingBefore, (array) DB::table('candidate_account_ids')->sole());
        $this->assertDatabaseCount('candidate_id_sequences', 0);
    }

    public function test_generation_candidate_notification_parses_replacement_values_only_once(): void
    {
        [$form] = $this->workflow('candidate_id_generation');
        $form->forceFill(['name' => json_encode(['en' => ':candidate_id', 'km' => ':candidate_id'])])->saveQuietly();
        $entry = $this->entry($form);
        $entry->forceFill(['candidate_id' => 'ID-:form', 'data' => [...$entry->data, 'first_name_en' => ':id', 'last_name_en' => '']])->saveQuietly();
        $entry->refresh();
        $before = $entry->getRawOriginal();
        $this->assertSame('Name=:id; Form=:candidate_id; ID=ID-:form / ID-:form', WorkflowMessagePlaceholders::render('Name=:candidate; Form=:form; ID=:id / :candidate_id', $entry));

        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::CandidateIdGeneration));

        $this->assertSame('Candidate message: :id | :candidate_id | ID-:form | ID-:form.', $this->candidate->notifications()->sole()->data['body']);
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertDatabaseCount('candidate_id_sequences', 0);
        $this->assertDatabaseCount('candidate_account_ids', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    #[DataProvider('locales')]
    public function test_all_staff_message_fields_show_the_localized_candidate_and_form_placeholder_guidance(string $locale): void
    {
        app()->setLocale($locale);
        $stages = [];
        foreach (['form_submission', 'review', 'payment', 'awaiting_results', 'completed'] as $type) {
            [, $workflow] = $this->workflow($type);
            $stages[] = $workflow->stages[0];
        }
        $workflow = WorkflowNotification::query()->create(['name' => 'Staff placeholder guidance', 'stages' => $stages]);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $fields = collect($component->instance()->form->getFlatFields())
            ->filter(fn ($field): bool => str_ends_with($field->getStatePath(), '.staff_notification_message'));
        $this->assertCount(7, $fields);
        foreach ($fields as $field) {
            $helper = __('workflow_notifications.helpers.staff_message_placeholders');
            $this->assertStringContainsString($helper, html_entity_decode(strip_tags($field->toHtml())));
            $this->assertNotSame('workflow_notifications.helpers.staff_message_placeholders', $helper);
            $this->assertStringContainsString(':candidate', $helper);
            $this->assertStringContainsString(':form', $helper);
            $this->assertSame(1000, $field->getMaxLength());
        }
    }

    private function deliver(CustomFormEntry $entry, string $type, ?string $action): void
    {
        if ($type === 'form_submission') {
            if (blank(data_get($entry->data, 'submitted_at'))) {
                $entry->update(['data' => [...$entry->data, 'submitted_at' => now()->toIso8601String()]]);
            }
            WorkflowSubmissionMessages::notifyCheckpoint($entry);
        } elseif ($type === 'review') {
            WorkflowStageMessages::notifyReviewAction($entry, $action);
        } else {
            WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::from($type));
        }
    }

    private function workflow(string $type): array
    {
        $form = CustomForm::query()->create([
            'name' => json_encode(['en' => 'Entrance application', 'km' => 'ពាក្យស្នើសុំប្រឡងចូល']),
            'slug' => 'staff-placeholders-'.uniqid(), 'is_active' => true,
        ]);
        $staffMessages = ['staff_notification_message' => self::BODY, 'staff_recipient_roles' => ['admin']];
        $stage = [
            'stage_type' => $type, 'stage_name' => 'Configured '.$type,
            'responsible_role' => ['admin'], 'notification_channels' => ['system'],
            'notification_message' => self::CANDIDATE_BODY, 'status_message' => 'Existing status',
        ];
        if ($type === 'review') {
            foreach (['accept', 'send_back', 'reject'] as $action) {
                $stage['review_actions'][$action] = [...$staffMessages, 'notification_message' => self::CANDIDATE_BODY];
            }
        } elseif ($type === 'form_submission') {
            $stage['submission_messages'] = [...$staffMessages, 'candidate_notification_message' => self::CANDIDATE_BODY];
        } else {
            $stage['audience_messages'] = $staffMessages;
        }
        $workflow = WorkflowNotification::query()->create(['name' => 'Staff placeholder workflow', 'stages' => [['type' => 'stage', 'data' => $stage]]]);
        $workflow->forms()->attach($form);

        return [$form, $workflow];
    }

    private function entry(CustomForm $form): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => 'pending',
            'data' => [
                'registration_status' => 'pending', 'candidate_status' => 'pending',
                'first_name_kh' => 'សុខ', 'last_name_kh' => 'ដារ៉ា',
                'first_name_en' => 'SOK', 'last_name_en' => 'DARA',
            ],
        ]);
    }

    private function user(string $type, string $locale): User
    {
        $name = 'staff_placeholder_'.$type.'_'.uniqid();

        return User::query()->forceCreate([
            'registration_type' => $type, 'name' => $name, 'username' => $name,
            'email' => $name.'@example.test', 'password' => 'password', 'is_active' => true,
            'date_of_birth' => '2000-01-01', 'locale' => $locale,
        ]);
    }
}
