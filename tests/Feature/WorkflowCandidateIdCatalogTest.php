<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType as StageBehavior;
use App\Filament\Admin\Resources\WorkflowButtons\Pages\CreateWorkflowButton;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\CreateWorkflowStageType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowButton;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
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

class WorkflowCandidateIdCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        $admin = User::query()->create([
            'registration_type' => 'admin', 'name' => 'ID catalog admin', 'username' => 'id-catalog-admin',
            'email' => 'id-catalog-admin@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public static function locales(): array
    {
        return [['en', 'Generate Candidate ID'], ['km', 'បង្កើតលេខបេក្ខជន']];
    }

    #[DataProvider('locales')]
    public function test_separate_id_action_can_be_selected_in_button_and_stage_forms(string $locale, string $label): void
    {
        app()->setLocale($locale);
        $this->assertSame($label, WorkflowButton::actionOptions()['generate_candidate_id']);
        $this->assertSame($label, StageBehavior::CandidateIdGeneration->label());
        Livewire::test(CreateWorkflowButton::class)
            ->fillForm(['label_en' => 'Issue ID at my checkpoint', 'label_km' => 'បង្កើតលេខតាមដំណាក់កាល', 'action_key' => 'generate_candidate_id',
                'icon' => 'heroicon-o-identification', 'color' => 'info', 'is_active' => true])
            ->call('create')->assertHasNoFormErrors();
        $button = WorkflowButton::query()->where('label_en', 'Issue ID at my checkpoint')->sole();
        Livewire::test(CreateWorkflowStageType::class)
            ->fillForm(['label_en' => 'My ID checkpoint', 'label_km' => 'ដំណាក់កាលបង្កើតលេខរបស់ខ្ញុំ', 'is_active' => true])
            ->set('data.workflow_button_ids', [$button->id])
            ->assertSet('data.behavior', 'candidate_id_generation')
            ->call('create')->assertHasNoFormErrors();
        $type = WorkflowStageType::query()->where('label_en', 'My ID checkpoint')->sole();
        $this->assertSame(['generate_candidate_id'], $type->enabledActionKeys());
        $this->assertSame('candidate_id_generation', $type->workflowType()->value);
        $this->assertSame('verify', WorkflowButton::query()->where('action_key', 'verify')->firstOrFail()->action_key);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_additive_catalog_migration_is_idempotent_and_preserves_existing_workflows_and_snapshots(): void
    {
        DB::table('workflow_stage_types')->where('behavior', 'candidate_id_generation')->delete();
        DB::table('workflow_buttons')->where('action_key', 'generate_candidate_id')->delete();
        $legacy = WorkflowStageType::query()->where('behavior', 'verification')->where('is_system', true)->sole();
        $workflow = WorkflowNotification::query()->create(['name' => 'Keep receipt flow', 'stages' => [['type' => 'stage', 'data' => [
            'stage_name' => 'Existing application receipt', 'stage_type' => 'verification', 'stage_type_id' => $legacy->id,
            'responsible_role' => ['admin'], 'notification_message' => 'Keep saved message',
        ]]]]);
        $form = CustomForm::query()->create(['name' => 'Keep receipt form', 'slug' => 'keep-id-catalog-form', 'is_active' => true]);
        $workflow->forms()->attach($form);
        $entry = CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->id, 'created_by' => auth()->id(),
            'review_status' => 'pending', 'data' => ['registration_status' => 'pending', 'submitted_at' => now()->toIso8601String()]]);
        $before = [
            'types' => DB::table('workflow_stage_types')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'buttons' => DB::table('workflow_buttons')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'workflow' => $workflow->fresh()->getRawOriginal(), 'entry' => $entry->fresh()->getRawOriginal(),
        ];
        // A custom name must remain untouched even if it matches the new default.
        $collision = WorkflowStageType::query()->create(['label_en' => 'Generate Candidate ID', 'label_km' => 'បង្កើតលេខបេក្ខជន', 'behavior' => 'review']);
        $migration = require database_path('migrations/2026_11_20_000004_add_candidate_id_generation_workflow_catalog.php');

        $migration->up();
        $migration->up();
        WorkflowStageType::selectOptions();

        $type = WorkflowStageType::query()->where('behavior', 'candidate_id_generation')->where('is_system', true)->sole();
        $button = WorkflowButton::query()->where('action_key', 'generate_candidate_id')->sole();
        $this->assertSame([$button->id], $type->workflow_button_ids);
        $this->assertSame(['generate_candidate_id'], $type->enabled_actions);
        $this->assertSame('Generate Candidate ID (2)', $type->label_en);
        $this->assertSame('Generate Candidate ID', $collision->fresh()->label_en);
        $this->assertSame('heroicon-o-identification', $button->icon);
        $this->assertSame('info', $button->color);
        foreach ($before['types'] as $row) {
            $this->assertSame($row, (array) DB::table('workflow_stage_types')->where('id', $row['id'])->first());
        }
        foreach ($before['buttons'] as $row) {
            $this->assertSame($row, (array) DB::table('workflow_buttons')->where('id', $row['id'])->first());
        }
        $this->assertSame($before['workflow'], $workflow->fresh()->getRawOriginal());
        $this->assertSame($before['entry'], $entry->fresh()->getRawOriginal());
        $this->assertSame('verification', $legacy->fresh()->behavior);
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_existing_inactive_or_customized_id_catalog_rows_are_never_overwritten_or_reactivated(): void
    {
        $type = WorkflowStageType::query()->where('behavior', 'candidate_id_generation')->where('is_system', true)->sole();
        $button = WorkflowButton::query()->where('action_key', 'generate_candidate_id')->firstOrFail();
        $type->update(['label_en' => 'Keep my ID wording', 'label_km' => 'រក្សាឈ្មោះរបស់ខ្ញុំ', 'is_active' => false]);
        $button->update(['label_en' => 'Keep inactive ID button', 'color' => 'warning', 'is_active' => false]);
        $beforeType = $type->fresh()->getRawOriginal();
        $beforeButton = $button->fresh()->getRawOriginal();

        WorkflowStageType::ensureCandidateIdGenerationAvailable();
        $this->assertArrayNotHasKey($type->id, WorkflowStageType::selectOptions());
        $this->assertArrayHasKey($type->id, WorkflowStageType::selectOptions($type->id));

        $this->assertSame($beforeType, $type->fresh()->getRawOriginal());
        $this->assertSame($beforeButton, $button->fresh()->getRawOriginal());
    }

    #[DataProvider('locales')]
    public function test_workflow_create_and_edit_preserve_id_stage_status_alert_channels_and_audience_settings(string $locale, string $label): void
    {
        app()->setLocale($locale);
        $component = Livewire::test(CreateWorkflowNotification::class);
        $key = array_key_first($component->get('data.stages'));
        $path = 'data.stages.'.$key.'.data';
        $component->set($path.'.stage_type_id', WorkflowStageType::selectionForBehavior('candidate_id_generation'));
        $component->assertSee($label);
        $fields = collect($component->instance()->form->getFlatFields())->keyBy(fn ($field): string => $field->getStatePath());
        foreach (['notification_channels', 'status_message', 'alert_message', 'responsible_role', 'notification_message', 'audience_messages.staff_notification_message', 'audience_messages.staff_recipient_roles'] as $field) {
            $this->assertTrue($fields->has($path.'.'.$field), 'Expected generation setting: '.$field);
        }
        $component->set('data.name', 'ID settings '.$locale)
            ->set($path.'.responsible_role', ['admin'])->set($path.'.notification_channels', ['system', 'email'])
            ->set($path.'.status_message', 'ID issued status')->set($path.'.alert_message', 'ID issued alert')
            ->set($path.'.notification_message', 'ID issued candidate message')
            ->set($path.'.audience_messages.staff_notification_message', 'ID issued staff message')
            ->set($path.'.audience_messages.staff_recipient_roles', ['admin'])
            ->call('create')->assertHasNoFormErrors();
        $workflow = WorkflowNotification::query()->where('name', 'ID settings '.$locale)->sole();
        $saved = $workflow->stages[0]['data'];
        $this->assertSame('candidate_id_generation', $saved['stage_type']);
        $this->assertSame('ID issued status', $saved['status_message']);
        $this->assertSame('ID issued alert', $saved['alert_message']);
        $this->assertSame(['system', 'email'], $saved['notification_channels']);
        $this->assertSame('ID issued candidate message', $saved['notification_message']);
        $this->assertSame(['staff_notification_message' => 'ID issued staff message', 'staff_recipient_roles' => ['admin']], $saved['audience_messages']);

        $edit = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $editKey = array_key_first($edit->get('data.stages'));
        $edit->set('data.stages.'.$editKey.'.data.status_message', 'Updated ID status')->call('save')->assertHasNoFormErrors();
        $this->assertSame('Updated ID status', $workflow->fresh()->stages[0]['data']['status_message']);
        $this->assertSame('ID issued candidate message', $workflow->fresh()->stages[0]['data']['notification_message']);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }
}
