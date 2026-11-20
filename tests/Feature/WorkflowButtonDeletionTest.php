<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType as StageBehavior;
use App\Filament\Admin\Resources\WorkflowButtons\Pages\EditWorkflowButton;
use App\Filament\Admin\Resources\WorkflowButtons\Pages\ListWorkflowButtons;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\ListWorkflowNotifications;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\EditWorkflowStageType;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\ListWorkflowStageTypes;
use App\Models\Major;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowButton;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use App\Services\WorkflowCandidateIdGenerator;
use App\Services\WorkflowCustomButtonService;
use App\Support\WorkflowButtonColors;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Database\Seeders\WorkflowButtonSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowButtonDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public static function deleters(): array
    {
        return ['administrator' => [true], 'delegated delete permission' => [false]];
    }

    #[DataProvider('deleters')]
    public function test_selected_generation_and_generic_buttons_reject_ui_and_direct_deletion_for_every_deleter(bool $administrator): void
    {
        $actor = $this->actor($administrator);
        $role = $administrator ? 'admin' : 'workflow-button-deleter';
        $candidate = $this->user('archived-button-candidate', 'student');
        $major = Major::query()->create(['key' => 'archived-button-major', 'abbreviation' => 'BNM', 'name_en' => 'Archived button major', 'is_active' => true]);
        $generate = WorkflowButton::query()->create(['label_en' => 'Retained generation button', 'action_key' => 'generate_candidate_id', 'color' => 'info']);
        $generic = WorkflowButton::query()->create(['label_en' => 'Retained receipt button', 'action_key' => null, 'color' => 'zinc']);
        $generation = $this->type('Archived generation stage', $generate);
        $receipt = $this->type('Archived receipt stage', $generic);
        $workflow = WorkflowNotification::query()->create(['name' => 'Archived buttons workflow', 'stages' => [
            ['type' => 'stage', 'data' => ['stage_name' => 'Generate ID', 'stage_type_id' => $generation->id, 'stage_type' => 'candidate_id_generation',
                'responsible_role' => [$role], 'notification_channels' => ['system'], 'notification_message' => 'Frozen generated ID notice', 'status_message' => 'Generated ID status']],
            ['type' => 'stage', 'data' => ['stage_name' => 'Record receipt', 'stage_type_id' => $receipt->id, 'stage_type' => 'custom',
                'responsible_role' => [$role], 'notification_channels' => ['system'], 'submission_messages' => ['candidate_status_message' => 'Archived receipt still works',
                    'candidate_notification_message' => 'Frozen receipt notice', 'staff_notification_message' => null, 'staff_recipient_roles' => []]]],
        ]]);
        $form = CustomForm::query()->create(['name' => 'Archived buttons form', 'slug' => 'archived-buttons-form', 'is_active' => true]);
        $workflow->forms()->attach($form);
        Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()])->call('save')->assertHasNoFormErrors();
        $workflow->refresh();
        $entry = CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->id, 'created_by' => $candidate->id, 'review_status' => 'pending',
            'data' => ['registration_status' => 'pending', 'candidate_status' => 'pending', 'major' => (string) $major->id, 'retained_answer' => 'Keep']])->fresh();
        WorkflowStageProgression::submitted($entry);
        $generic->update(['color' => 'info']);
        $entryBefore = $entry->getRawOriginal();
        $workflowStages = $workflow->getRawOriginal('stages');
        $generationBefore = $generation->fresh()->getRawOriginal();
        $receiptBefore = $receipt->fresh()->getRawOriginal();
        $generationDefinition = $generation->buttonDefinitions();
        $receiptDefinition = $receipt->buttonDefinitions();

        if ($administrator) {
            Livewire::test(EditWorkflowButton::class, ['record' => $generate->getRouteKey()])
                ->assertActionHidden('delete')->call('mountAction', 'delete')->call('callMountedAction');
        } else {
            $this->assertFalse($actor->can('Update:WorkflowButton'));
            Livewire::test(ListWorkflowButtons::class)->assertTableActionHidden('edit', $generate);
        }

        foreach ([$generate, $generic] as $button) {
            Livewire::test(ListWorkflowButtons::class)->assertTableActionHidden('delete', $button)
                ->mountTableAction('delete', $button)->callMountedTableAction();
            $this->assertTrue($button->fresh()->inUse());
            $this->assertFalse($button->fresh()->canDelete());
            $this->assertRejected(fn () => $button->fresh()->delete());
            $this->assertRejected(fn () => $button->fresh()->forceDelete());
            $this->assertNotSoftDeleted($button);
            $this->assertNotNull(WorkflowButton::query()->find($button->id));
            $this->assertArrayHasKey($button->id, WorkflowButton::selectOptions());
            $this->assertArrayHasKey($button->id, WorkflowButton::selectOptions([$button->id]));
        }
        Livewire::test(ListWorkflowButtons::class)->set('tableRecordsPerPage', 25)->assertCanSeeTableRecords([$generate, $generic]);
        $this->assertSame($generationBefore, $generation->fresh()->getRawOriginal());
        $this->assertSame($receiptBefore, $receipt->fresh()->getRawOriginal());
        $this->assertSame($entryBefore, $entry->fresh()->getRawOriginal());
        $this->assertSame($workflowStages, $workflow->fresh()->getRawOriginal('stages'));
        $this->assertSame($generationDefinition, $generation->fresh()->buttonDefinitions());
        $this->assertSame($receiptDefinition, $receipt->fresh()->buttonDefinitions());
        $this->assertSame('info', WorkflowButtonColors::forDefinition($entry->workflow_snapshot['stages'][1]['data']['buttons'][0]));

        foreach ([$generation, $receipt] as $type) {
            Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()])->call('save')->assertHasNoFormErrors();
            $this->assertSame($type->workflow_button_ids, $type->fresh()->workflow_button_ids);
            $this->assertSame($type->behavior, $type->fresh()->behavior);
        }
        Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()])->call('save')->assertHasNoFormErrors();
        $this->assertSame($workflowStages, $workflow->fresh()->getRawOriginal('stages'));
        $this->assertSame($entryBefore, $entry->fresh()->getRawOriginal());
        Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible('generate_candidate_id', $entry)
            ->callTableAction('generate_candidate_id', $entry)->assertHasNoTableActionErrors();
        $entry->refresh();
        $this->assertSame('BNM-0001', $entry->candidate_id);
        $this->assertSame(StageBehavior::Custom, WorkflowStageProgression::currentType($entry));
        $this->assertSame('BNM-0001', app(WorkflowCandidateIdGenerator::class)->generate($entry));
        $this->assertTrue(app(WorkflowCustomButtonService::class)->execute($entry, 1, $generic->id));
        $this->assertTrue(WorkflowStageProgression::finished($entry));
        $this->assertSame('Archived receipt still works', WorkflowStageMessages::statusText($entry));
        $this->assertSame($entryBefore['workflow_snapshot'], $entry->getRawOriginal('workflow_snapshot'));
        $this->assertSame('Keep', data_get($entry->data, 'retained_answer'));
        $this->assertEqualsCanonicalizing(['Frozen generated ID notice', 'Frozen receipt notice'], $candidate->notifications()->get()->pluck('data.body')->all());
        $this->assertDatabaseHas('candidate_id_sequences', ['custom_form_id' => $form->id, 'last_sequence' => 1]);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_unused_defaults_can_be_archived_and_are_not_recreated(): void
    {
        $this->actor(true);
        $button = WorkflowButton::query()->where('action_key', 'notify')->firstOrFail();
        $this->assertFalse($button->inUse());
        Livewire::test(ListWorkflowButtons::class)->callTableAction('delete', $button);
        $this->assertSoftDeleted($button);
        $before = WorkflowButton::withTrashed()->orderBy('id')->get()->map(fn (WorkflowButton $row): array => $row->getRawOriginal())->all();

        (new WorkflowButtonSeeder)->run();
        WorkflowStageType::ensureCandidateIdGenerationAvailable();
        WorkflowStageType::selectOptions();
        (new WorkflowButtonSeeder)->run();

        $this->assertSame($before, WorkflowButton::withTrashed()->orderBy('id')->get()->map(fn (WorkflowButton $row): array => $row->getRawOriginal())->all());
        $this->assertSame(0, WorkflowButton::query()->where('action_key', 'notify')->count());
        $this->assertSame(1, WorkflowButton::withTrashed()->where('action_key', 'notify')->count());
        $this->assertSoftDeleted($button);
    }

    public function test_template_links_control_button_archival_and_stage_links_only_protect_permanent_deletion(): void
    {
        $actor = $this->actor(true);
        $button = WorkflowButton::query()->create(['label_en' => 'Dependency chain receipt', 'action_key' => null]);
        $stage = $this->type('Dependency chain stage', $button);
        $workflow = WorkflowNotification::query()->create(['name' => 'Dependency chain template', 'stages' => [['type' => 'stage', 'data' => [
            'stage_name' => 'Retained receipt', 'stage_type_id' => $stage->id, 'stage_type' => 'custom', 'responsible_role' => ['admin'],
            'notification_channels' => [], 'submission_messages' => ['candidate_status_message' => 'Frozen historical receipt'],
        ]]]]);
        $form = CustomForm::query()->create(['name' => 'Dependency chain form', 'slug' => 'dependency-chain-form', 'is_active' => true]);
        $workflow->forms()->attach($form);
        $entry = CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->id, 'created_by' => $actor->id, 'review_status' => 'pending',
            'data' => ['registration_status' => 'pending', 'retained_answer' => 'Keep chain']])->fresh();
        WorkflowStageProgression::submitted($entry);
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $entryBefore = $entry->getRawOriginal();
        $unused = WorkflowNotification::query()->create(['name' => 'Unused bulk deletion template', 'stages' => []]);

        $this->assertFalse($button->canDelete());
        $this->assertFalse($stage->canDelete());
        $this->assertFalse($workflow->canDelete());
        Livewire::test(ListWorkflowStageTypes::class)->assertTableActionHidden('delete', $stage)
            ->mountTableAction('delete', $stage)->callMountedTableAction();
        Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()])
            ->assertActionHidden('delete')->call('mountAction', 'delete')->call('callMountedAction');
        $this->assertRejected(fn () => $stage->fresh()->delete());
        $this->assertRejected(fn () => $workflow->fresh()->delete());
        Livewire::test(ListWorkflowNotifications::class)->assertTableActionHidden('delete', $workflow)
            ->callTableBulkAction('delete', [$workflow, $unused]);
        $this->assertModelExists($workflow);
        $this->assertModelMissing($unused);
        $this->assertDatabaseHas('workflow_notification_forms', ['workflow_notification_id' => $workflow->id, 'custom_form_id' => $form->id]);
        $this->assertSame($entryBefore, $entry->fresh()->getRawOriginal());

        $workflow->forms()->detach($form);
        $this->assertTrue($workflow->fresh()->canDelete());
        Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()])->callAction('delete');
        $this->assertModelMissing($workflow);
        $this->assertTrue($button->fresh()->inUse());
        $this->assertFalse($button->fresh()->usedByWorkflowTemplate());
        $this->assertTrue($button->fresh()->canDelete());
        $stageDefinitions = $stage->fresh()->buttonDefinitions();
        $nextTemplate = WorkflowNotification::query()->create(['name' => 'New current template', 'stages' => $workflow->stages]);
        $this->assertTrue($button->fresh()->usedByWorkflowTemplate());
        $this->assertFalse($button->fresh()->canDelete());
        $nextTemplate->delete();
        $this->assertFalse($button->fresh()->usedByWorkflowTemplate());
        $this->assertTrue($button->fresh()->canDelete());
        Livewire::test(ListWorkflowButtons::class)->callTableAction('delete', $button);
        $this->assertSoftDeleted($button);
        $this->assertModelExists($stage);
        $this->assertSame($stageDefinitions, $stage->fresh()->buttonDefinitions());
        $this->assertRejected(fn () => WorkflowButton::withTrashed()->findOrFail($button->id)->forceDelete());
        $futureTemplate = WorkflowNotification::query()->create(['name' => 'Retained stage after archival', 'stages' => [['type' => 'stage', 'data' => [
            'stage_type_id' => $stage->id, 'stage_type' => 'custom', 'stage_name' => 'Retained configured stage',
        ]]]]);
        $this->assertSame($button->id, $futureTemplate->stages[0]['data']['buttons'][0]['id']);
        $futureTemplate->delete();
        $this->assertTrue($stage->fresh()->canDelete());
        Livewire::test(ListWorkflowStageTypes::class)->callTableAction('delete', $stage)->assertHasNoTableActionErrors();
        $this->assertModelMissing($stage);
        $this->assertTrue(WorkflowButton::withTrashed()->findOrFail($button->id)->forceDelete());
        $this->assertNull(WorkflowButton::withTrashed()->find($button->id));
        $this->assertSame($snapshot, $entry->fresh()->getRawOriginal('workflow_snapshot'));
        $this->assertTrue(app(WorkflowCustomButtonService::class)->execute($entry, 0, $button->id));
        $this->assertSame('Frozen historical receipt', WorkflowStageMessages::statusText($entry));
        $this->assertSame('Keep chain', data_get($entry->data, 'retained_answer'));
    }

    public function test_direct_template_button_references_protect_a_button_without_a_catalog_stage_reference(): void
    {
        $this->actor(true);
        $button = WorkflowButton::query()->create(['label_en' => 'Direct template check', 'action_key' => 'accept']);
        $workflow = WorkflowNotification::query()->create(['name' => 'Direct button template', 'stages' => [['type' => 'stage', 'data' => [
            'stage_name' => 'Legacy check', 'stage_type' => 'review', 'buttons' => [$button->toDefinition()],
        ]]]]);
        $this->assertTrue($button->inUse());
        Livewire::test(ListWorkflowButtons::class)->assertTableActionHidden('delete', $button)
            ->mountTableAction('delete', $button)->callMountedTableAction();
        $this->assertRejected(fn () => $button->fresh()->delete());
        $this->assertNotSoftDeleted($button);

        Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()])->callAction('delete');
        $this->assertTrue($button->fresh()->canDelete());
        Livewire::test(ListWorkflowButtons::class)->callTableAction('delete', $button);
        $this->assertSoftDeleted($button);
    }

    public function test_saved_template_buttons_are_authoritative_over_the_current_stage_selection(): void
    {
        $this->actor(true);
        $selected = WorkflowButton::query()->create(['label_en' => 'Current stage selection', 'action_key' => 'accept']);
        $saved = WorkflowButton::query()->create(['label_en' => 'Saved template selection', 'action_key' => 'accept']);
        $stage = $this->type('Saved selection precedence', $selected);
        $data = ['stage_type_id' => $stage->id, 'stage_type' => 'review', 'stage_name' => 'Legacy explicit stage'];
        $workflow = WorkflowNotification::withoutEvents(fn (): WorkflowNotification => WorkflowNotification::query()->create([
            'name' => 'Saved button authority', 'stages' => [['type' => 'stage', 'data' => $data]],
        ]));
        $this->assertTrue($selected->usedByWorkflowTemplate());
        $this->assertFalse($saved->usedByWorkflowTemplate());

        WorkflowNotification::withoutEvents(fn () => $workflow->update(['stages' => [['type' => 'stage', 'data' => [...$data, 'buttons' => [$saved->toDefinition()]]]]]));

        $this->assertFalse($selected->fresh()->usedByWorkflowTemplate());
        $this->assertTrue($selected->fresh()->canDelete());
        $this->assertTrue($selected->fresh()->inUse());
        $this->assertTrue($saved->fresh()->usedByWorkflowTemplate());
        $this->assertFalse($saved->fresh()->canDelete());
        $this->assertRejected(fn () => $selected->fresh()->update(['action_key' => 'paid']));

        WorkflowNotification::withoutEvents(fn () => $workflow->update(['stages' => [['type' => 'stage', 'data' => [...$data, 'buttons' => []]]]]));

        $this->assertFalse($selected->fresh()->usedByWorkflowTemplate());
        $this->assertFalse($saved->fresh()->usedByWorkflowTemplate());
        $this->assertTrue($selected->fresh()->canDelete());
        $this->assertTrue($saved->fresh()->canDelete());
    }

    public function test_legacy_behavior_never_guesses_button_clones_or_default_ids(): void
    {
        $this->actor(true);
        $default = WorkflowButton::query()->where('action_key', 'accept')->firstOrFail();
        $clone = WorkflowButton::query()->create(['label_en' => 'Unrelated accept clone', 'action_key' => 'accept']);
        $this->type('Unrelated custom review', $clone);
        $system = WorkflowStageType::query()->where('behavior', 'review')->where('is_system', true)->firstOrFail();
        $system->update(['workflow_button_ids' => null]);
        WorkflowNotification::query()->create(['name' => 'Legacy approval mapping', 'stages' => [['type' => 'stage', 'data' => [
            'stage_type' => 'approval', 'stage_name' => 'Legacy approval without button definitions',
        ]]]]);
        $this->assertFalse($default->usedByWorkflowTemplate());
        $this->assertFalse($clone->usedByWorkflowTemplate());
        $this->assertTrue($default->canDelete());
        $this->assertTrue($clone->canDelete());

        $system->update(['workflow_button_ids' => [$default->id]]);

        $this->assertTrue($default->fresh()->usedByWorkflowTemplate());
        $this->assertFalse($default->fresh()->canDelete());
        $this->assertFalse($clone->fresh()->usedByWorkflowTemplate());
        $this->assertTrue($clone->fresh()->canDelete());
    }

    private function assertRejected(callable $callback): void
    {
        try {
            $callback();
            $this->fail('A linked catalog record must not be deleted.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    private function type(string $label, WorkflowButton $button): WorkflowStageType
    {
        return WorkflowStageType::query()->create(['label_en' => $label, 'label_km' => 'ប្រភេទ '.$label, 'workflow_button_ids' => [$button->id]]);
    }

    private function actor(bool $administrator): User
    {
        $actor = $this->user('button-deletion-actor', 'admin');
        $role = Role::findOrCreate($administrator ? 'admin' : 'workflow-button-deleter', 'web');
        $actor->assignRole($role);
        if (! $administrator) {
            foreach (['ViewAny:WorkflowButton', 'Delete:WorkflowButton', 'ViewAny:WorkflowStageType', 'Update:WorkflowStageType',
                'Create:WorkflowStageType', 'ViewAny:WorkflowNotification', 'Update:WorkflowNotification', 'ViewAny:CustomFormEntry', 'View:CustomFormEntry'] as $permission) {
                $actor->givePermissionTo(Permission::findOrCreate($permission, 'web'));
            }
        }
        $this->actingAs($actor);

        return $actor;
    }

    private function user(string $name, string $registrationType): User
    {
        return User::query()->create(['registration_type' => $registrationType, 'name' => $name, 'username' => $name,
            'email' => $name.'@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
    }
}
