<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\CandidateEntranceStatisticResource;
use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Models\Major;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType as StageTypeCatalog;
use App\Services\WorkflowCandidateVerifier;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class WorkflowNullableStageTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    private Major $major;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        $admin = $this->user('admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
        $this->candidate = $this->user('student');
        $this->major = Major::query()->create(['key' => 'nullable-nurse', 'abbreviation' => 'BNM', 'name_en' => 'Nurse Midwifery', 'is_active' => true]);
    }

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    #[DataProvider('locales')]
    public function test_native_create_and_edit_allow_clearing_type_and_role_while_names_stay_required(string $locale): void
    {
        app()->setLocale($locale);
        $component = Livewire::test(CreateWorkflowNotification::class)->set('data.stages', [])
            ->fillForm(['name' => 'Nullable stage '.$locale, 'stages' => [$this->blank('Keep this draft stage')]]);
        $key = array_key_first($component->get('data.stages'));
        $path = 'data.stages.'.$key.'.data';
        $fields = collect($component->instance()->form->getFlatFields(withHidden: true));
        foreach (['stage_type_id', 'stage_type', 'responsible_role'] as $name) {
            $field = $fields->first(fn ($field): bool => $field->getStatePath() === $path.'.'.$name);
            $this->assertNotNull($field);
            $this->assertFalse($field->isRequired());
        }
        $component->call('create')->assertHasNoFormErrors();
        $workflow = WorkflowNotification::query()->where('name', 'Nullable stage '.$locale)->sole();
        $this->assertSame('Keep this draft stage', data_get($workflow->stages, '0.data.stage_name'));
        $this->assertNull(data_get($workflow->stages, '0.data.stage_type'));

        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_key_first($component->get('data.stages'));
        $path = 'data.stages.'.$key.'.data';
        $component->set($path.'.stage_type_id', StageTypeCatalog::selectionForBehavior('review'))
            ->set($path.'.responsible_role', [])->call('save')->assertHasNoFormErrors();
        $this->assertSame('review', data_get($workflow->fresh()->stages, '0.data.stage_type'));
        $this->assertNull(data_get($workflow->fresh()->stages, '0.data.responsible_role'));
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_key_first($component->get('data.stages'));
        $path = 'data.stages.'.$key.'.data';
        $component->assertSet($path.'.stage_type', 'review')
            ->set($path.'.stage_type_id', null)->assertSet($path.'.stage_type', null)
            ->assertSet($path.'.enabled_actions', null)->call('save')->assertHasNoFormErrors();
        $this->assertNull(data_get($workflow->fresh()->stages, '0.data.stage_type'));
        $this->assertArrayNotHasKey('enabled_actions', data_get($workflow->fresh()->stages, '0.data'));
        $this->assertSame('Keep this draft stage', data_get($workflow->fresh()->stages, '0.data.stage_name'));
        $before = $workflow->fresh()->getRawOriginal();
        $component->set('data.name', null)->call('save')->assertHasErrors(['data.name' => 'required']);
        $component->set('data.name', 'Nullable stage '.$locale)->set($path.'.stage_name', null)
            ->call('save')->assertHasErrors([$path.'.stage_name' => 'required']);
        $this->assertSame($before, $workflow->fresh()->getRawOriginal());
    }

    public function test_invalid_nonempty_catalog_values_and_duplicate_types_still_fail_native_validation(): void
    {
        $component = Livewire::test(CreateWorkflowNotification::class)->set('data.stages', [])
            ->fillForm(['name' => 'Invalid optional type', 'stages' => [$this->blank('Named stage')]]);
        $key = array_key_first($component->get('data.stages'));
        $component->set('data.stages.'.$key.'.data.stage_type_id', 999999)->call('create')->assertHasFormErrors();
        $this->assertDatabaseMissing('workflow_notifications', ['name' => 'Invalid optional type']);
        $review = $this->stage('review');
        $review['data']['stage_type_id'] = StageTypeCatalog::selectionForBehavior('review');
        Livewire::test(CreateWorkflowNotification::class)->set('data.stages', [])
            ->fillForm(['name' => 'Invalid repeated type', 'stages' => [$review, $this->blank('Draft in between'), $review]])
            ->call('create')->assertHasFormErrors(['stages']);
        $this->assertDatabaseMissing('workflow_notifications', ['name' => 'Invalid repeated type']);
    }

    public function test_blank_template_rows_are_preserved_but_only_typed_stages_enter_the_ordered_snapshot(): void
    {
        [$form, $workflow] = $this->workflow([
            $this->blank('Before review'), $this->stage('review'), $this->blank('Future stage'),
            $this->stage('verification'), $this->stage('payment'), $this->blank('After payment'),
        ]);
        $entry = $this->entry($form);
        $this->assertCount(6, $workflow->fresh()->stages);
        $this->assertSame(2, $entry->workflow_snapshot['schema_version']);
        $this->assertSame(['review', 'verification', 'payment'], collect($entry->workflow_snapshot['stages'])->pluck('data.stage_type')->all());
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry));
        $this->check($entry);
        $this->assertNull($entry->candidate_id);
        $this->assertSame(WorkflowStageType::Verification, WorkflowStageProgression::currentType($entry));
        $this->assertSame([0], array_keys(data_get($entry->data, 'workflow_progress.completed_at')));
        $this->assertSame('BNM-0001', app(WorkflowCandidateVerifier::class)->verify($entry));
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageProgression::currentType($entry));
        $this->assertSame(2, data_get($entry->data, 'workflow_progress.current_index'));
        $this->assertSame([0, 1], array_keys(data_get($entry->data, 'workflow_progress.completed_at')));
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $this->assertSame(['Before review', 'Review', 'Future stage', 'Verification', 'Payment', 'After payment'], collect($workflow->fresh()->stages)->pluck('data.stage_name')->all());
    }

    public function test_a_configured_workflow_with_only_untyped_rows_stays_inert_without_static_fallback(): void
    {
        $draft = $this->blank('Named draft');
        $draft['data']['notification_message'] = 'Must never be sent';
        $draft['data']['notification_channels'] = ['system'];
        [$form] = $this->workflow([$draft, $this->blank('Another future step')]);
        $entry = $this->entry($form);
        $before = $this->row($entry);
        $this->assertSame(2, $entry->workflow_snapshot['schema_version']);
        $this->assertTrue($entry->workflow_snapshot['has_workflow']);
        $this->assertSame([], $entry->workflow_snapshot['stages']);
        $this->assertNull(WorkflowStageProgression::currentType($entry));
        $this->assertFalse(WorkflowStageProgression::finished($entry));
        $this->assertFalse(WorkflowStageProgression::readyForFinal($entry));
        $this->assertFalse(WorkflowStageProgression::advance($entry));
        $this->assertFalse(WorkflowStageProgression::submitted($entry));
        $this->assertFalse(WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Review));
        $this->assertFalse(app(WorkflowCandidateVerifier::class)->canVerify($entry));
        Livewire::test(ListCustomFormEntries::class)->assertTableActionHidden('accepted', $entry)->assertTableActionHidden('verify', $entry);
        $this->assertFalse(PaymentStatusListResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $this->assertFalse(CandidateEntranceStatisticResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $this->assertFalse(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $this->assertSame($before, $this->row($entry));
        $this->assertSame('pending', $entry->review_status);
        $this->assertNull($entry->candidate_id);
        $this->assertDatabaseCount('candidate_id_sequences', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public static function staffStages(): array
    {
        return [['review'], ['verification']];
    }

    #[DataProvider('staffStages')]
    public function test_empty_responsible_role_denies_actions_while_older_snapshot_roles_remain_frozen(string $type): void
    {
        [$form, $workflow] = $this->workflow([$this->stage('review'), $this->stage('verification'), $this->stage('payment')]);
        $old = $this->entry($form);
        $oldSnapshot = $old->getRawOriginal('workflow_snapshot');
        $oldRow = $this->row($old);
        $stages = $workflow->stages;
        $stages[$type === 'review' ? 0 : 1]['data']['responsible_role'] = null;
        $stages[] = $this->blank('Future role assignment');
        $workflow->update(['stages' => $stages]);
        $this->assertSame($oldRow, $this->row($old));
        $new = $this->entry($form);
        if ($type === 'verification') {
            $this->check($old);
            $this->check($new);
        }
        DocumentTemplate::query()->where('type', 'custom_form_'.$form->id)->delete();
        $stageType = WorkflowStageType::from($type);
        $this->assertTrue(WorkflowStageMessages::canCurrentUserHandleStage($old, $stageType));
        $this->assertFalse(WorkflowStageMessages::canCurrentUserHandleStage($new, $stageType));
        $action = $type === 'review' ? 'accepted' : 'verify';
        $component = Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible($action, $old)->assertTableActionHidden($action, $new);
        $before = $this->row($new);
        try {
            if ($type === 'review') {
                $component->instance()->getTable()->getAction('accepted')->getActionFunction()($new);
            } else {
                app(WorkflowCandidateVerifier::class)->verify($new);
            }
            $this->fail('An empty responsible role must not authorize a staff action.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame($before, $this->row($new));
        $this->assertSame($oldSnapshot, $old->fresh()->getRawOriginal('workflow_snapshot'));
        $this->assertDatabaseCount('candidate_id_sequences', 0);
    }

    private function workflow(array $stages): array
    {
        $form = CustomForm::query()->create(['name' => 'Nullable workflow application', 'slug' => 'nullable-'.uniqid(), 'is_active' => true, 'requires_payment' => true]);
        $workflow = WorkflowNotification::query()->create(['name' => 'Nullable workflow '.uniqid(), 'stages' => $stages]);
        $workflow->forms()->attach($form);

        return [$form, $workflow];
    }

    private function stage(string $type): array
    {
        return ['type' => 'stage', 'data' => ['stage_name' => ucfirst($type), 'stage_type' => $type, 'responsible_role' => ['admin'], 'payment_source' => 'system', 'notification_channels' => []]];
    }

    private function blank(string $name): array
    {
        return ['type' => 'stage', 'data' => ['stage_name' => $name, 'stage_type_id' => null, 'stage_type' => null, 'responsible_role' => null]];
    }

    private function entry(CustomForm $form): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => 'pending', 'data' => ['registration_status' => 'pending', 'candidate_status' => 'pending', 'major' => (string) $this->major->id]]);
    }

    private function check(CustomFormEntry $entry): void
    {
        DocumentTemplate::query()->where('type', 'custom_form_'.$entry->custom_form_id)->delete();
        Livewire::test(ListCustomFormEntries::class)->callTableAction('accepted', $entry)->assertHasNoTableActionErrors();
        $entry->refresh();
    }

    private function row(CustomFormEntry $entry): array
    {
        return (array) DB::table('custom_form_entries')->where('id', $entry->id)->first();
    }

    private function user(string $type): User
    {
        $name = 'nullable-'.$type.'-'.uniqid();

        return User::query()->forceCreate(['registration_type' => $type, 'name' => $name, 'username' => $name, 'email' => $name.'@example.test', 'password' => 'password', 'is_active' => true, 'date_of_birth' => '2000-01-01']);
    }
}
