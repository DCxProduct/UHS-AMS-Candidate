<?php

namespace Tests\Feature;

use App\Filament\Livewire\DatabaseNotifications;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class WorkflowReviewAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $reviewer;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
        $this->reviewer = $this->user('admin', 'registrar_officer');
        $this->grant($this->reviewer, ['ViewAny', 'View']);
        $this->candidate = $this->user('student', 'candidate');
        $this->actingAs($this->reviewer);
    }

    public static function configuredDecisions(): array
    {
        return [
            'row checked' => ['row', 'accepted', 'approved'],
            'row send back' => ['row', 'rejected', 'rejected'],
            'row reject' => ['row', 'reject', 'failed'],
            'PDF checked' => ['modal', 'approve_from_view', 'approved'],
            'PDF send back' => ['modal', 'reject_from_view', 'rejected'],
            'PDF reject' => ['modal', 'reject_final_from_view', 'failed'],
            'HTTP checked' => ['http', 'approve', 'approved'],
            'HTTP send back' => ['http', 'reject', 'rejected'],
        ];
    }

    #[DataProvider('configuredDecisions')]
    public function test_saved_review_role_authorizes_each_review_path_without_action_permissions(string $path, string $action, string $status): void
    {
        [$entry, $workflow] = $this->application();
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $stages = $workflow->stages;
        $stages[0]['data']['responsible_role'] = ['cashier_officer'];
        $workflow->update(['stages' => $stages]);
        $workflow->forms()->detach($entry->custom_form_id);
        $replacement = WorkflowNotification::query()->create(['name' => 'Replacement workflow', 'stages' => $stages]);
        $replacement->forms()->attach($entry->custom_form_id);
        foreach (['Accepted', 'Rejected', 'Reject', 'ViewPdf'] as $permission) {
            $this->assertFalse($this->reviewer->can($permission.':CustomFormEntry'));
        }

        if ($path === 'http') {
            $this->document($entry);
            $this->fakePdf();
            $this->get(route('admin.custom-form-entries.pdf-inline', $entry))->assertOk()->assertSee('%PDF-test');
            $this->post(route('admin.custom-form-entries.'.$action, $entry), ['review_note' => 'Configured role decision'])->assertRedirect();
        } else {
            if ($path === 'modal') {
                $this->document($entry);
            }
            $component = Livewire::test(ListCustomFormEntries::class);
            if ($path === 'modal') {
                $component->assertTableActionVisible('view_template_pdf', $entry)
                    ->mountTableAction('view_template_pdf_modal', $entry);
                $this->assertContains($action, array_keys($component->instance()->getMountedAction()->getVisibleModalFooterActions()));
                $component->callAction($action, ['review_note' => 'Configured role decision'])->assertHasNoErrors();
            } else {
                $component->assertTableActionVisible($action, $entry)
                    ->callTableAction($action, $entry, $action === 'accepted' ? [] : ['review_note' => 'Configured role decision'])->assertHasNoTableActionErrors();
            }
        }

        $entry->refresh();
        $this->assertSame($status, $entry->review_status);
        $this->assertSame($status, $entry->data['registration_status']);
        $this->assertSame('Original answer', $entry->data['answer']);
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $this->assertSame($this->reviewer->id, (int) $entry->reviewed_by);
        $this->assertSame($status === 'approved' ? null : 'Configured role decision', $entry->review_note);
        $this->assertNull($entry->candidate_id);
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public static function staticDecisions(): array
    {
        return [['accepted', 'Accepted', 'approved'], ['rejected', 'Rejected', 'rejected'], ['reject', 'Reject', 'failed']];
    }

    #[DataProvider('staticDecisions')]
    public function test_static_review_still_requires_its_original_action_permission(string $action, string $permission, string $status): void
    {
        [$entry] = $this->application(false);
        $before = $this->row($entry);
        $auditCount = $this->entryAuditCount($entry);
        $component = Livewire::test(ListCustomFormEntries::class)->assertTableActionHidden($action, $entry);
        $callback = $component->instance()->getTable()->getAction($action)->getActionFunction();
        $this->assertDenied(fn () => $callback($entry, ['review_note' => 'Forged review']));
        $this->assertSame($before, $this->row($entry));
        $this->assertSame($auditCount, $this->entryAuditCount($entry));
        $this->grant($this->reviewer, [$permission]);
        Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible($action, $entry)
            ->callTableAction($action, $entry, $action === 'accepted' ? [] : ['review_note' => 'Permitted static review'])->assertHasNoTableActionErrors();
        $this->assertSame($status, $entry->fresh()->review_status);
        $this->assertNull($entry->candidate_id);
    }

    public function test_wrong_frozen_role_missing_view_manager_access_and_candidates_cannot_forge_review(): void
    {
        [$entry] = $this->application();
        $before = $this->row($entry);
        $auditCount = $this->entryAuditCount($entry);
        $component = Livewire::test(ListCustomFormEntries::class);
        $callback = $component->instance()->getTable()->getAction('rejected')->getActionFunction();
        $wrongRole = $this->user('admin', 'cashier_officer');
        $this->grant($wrongRole, ['ViewAny', 'View', 'Accepted', 'Rejected', 'Reject', 'ViewPdf']);
        $this->actingAs($wrongRole);
        Livewire::test(ListCustomFormEntries::class)->assertTableActionHidden('accepted', $entry)->assertTableActionHidden('rejected', $entry)->assertTableActionHidden('reject', $entry);
        $this->assertDenied(fn () => $callback($entry, ['review_note' => 'Wrong role']));
        $this->post(route('admin.custom-form-entries.approve', $entry))->assertForbidden();
        $this->post(route('admin.custom-form-entries.reject', $entry), ['review_note' => 'Wrong role'])->assertForbidden();

        $this->actingAs($this->reviewer);
        foreach (['View', 'ViewAny'] as $permission) {
            $this->reviewer->revokePermissionTo($permission.':CustomFormEntry')->unsetRelation('permissions');
            $this->assertDenied(fn () => $callback($entry, ['review_note' => 'Revoked access']));
            $this->post(route('admin.custom-form-entries.approve', $entry))->assertForbidden();
            $this->grant($this->reviewer, [$permission]);
        }
        $this->grant($this->candidate, ['ViewAny', 'View', 'Accepted', 'Rejected', 'Reject', 'ViewPdf']);
        $this->actingAs($this->candidate);
        $this->assertDenied(fn () => $callback($entry, ['review_note' => 'Candidate forged review']));
        $this->post(route('admin.custom-form-entries.approve', $entry))->assertForbidden();
        $this->assertSame($before, $this->row($entry));
        $this->assertSame($auditCount, $this->entryAuditCount($entry));
    }

    public function test_pdf_opening_uses_current_review_role_but_keeps_static_and_historical_view_permissions(): void
    {
        [$configured] = $this->application();
        $this->document($configured);
        $this->fakePdf();
        Livewire::test(DatabaseNotifications::class)->call('openEntryReviewModal', (string) $configured->id)->assertActionMounted('applicationReview');
        $this->get(route('admin.custom-form-entries.pdf-inline', $configured))->assertOk();
        $this->post(route('admin.custom-form-entries.approve', $configured))->assertRedirect();
        $historical = $this->row($configured);
        $this->get(route('admin.custom-form-entries.pdf-inline', $configured))->assertForbidden();
        $this->grant($this->reviewer, ['ViewPdf']);
        $this->get(route('admin.custom-form-entries.pdf-inline', $configured))->assertOk();
        $this->assertSame($historical, $this->row($configured));

        [$static] = $this->application(false);
        $this->document($static);
        $before = $this->row($static);
        $this->reviewer->revokePermissionTo('ViewPdf:CustomFormEntry')->unsetRelation('permissions');
        Livewire::test(DatabaseNotifications::class)->call('openEntryReviewModal', (string) $static->id)->assertForbidden();
        $this->get(route('admin.custom-form-entries.pdf-inline', $static))->assertForbidden();
        $this->grant($this->reviewer, ['ViewPdf']);
        Livewire::test(DatabaseNotifications::class)->call('openEntryReviewModal', (string) $static->id)->assertActionMounted('applicationReview');
        $this->get(route('admin.custom-form-entries.pdf-inline', $static))->assertOk();
        $this->assertSame($before, $this->row($static));
    }

    public static function staleDecisions(): array
    {
        return [
            ['row', 'rejected', 'pending'], ['row', 'reject', 'pending'],
            ['modal', 'reject_from_view', 'pending'], ['modal', 'reject_final_from_view', 'pending'],
            ['row', 'send_back_after_reject', 'failed'], ['modal', 'send_back_after_reject_from_view', 'failed'],
        ];
    }

    #[DataProvider('staleDecisions')]
    public function test_mounted_return_or_reject_callback_cannot_overwrite_a_fresh_review_decision(string $path, string $action, string $status): void
    {
        [$entry] = $this->application($status !== 'failed');
        if ($status === 'failed') {
            $this->grant($this->reviewer, ['Rejected', 'ViewPdf']);
        }
        if ($status !== 'pending') {
            $data = array_replace($entry->data, ['registration_status' => $status]);
            DB::table('custom_form_entries')->where('id', $entry->id)->update(['review_status' => $status, 'data' => json_encode($data)]);
            $entry->refresh();
        }
        if ($path === 'modal') {
            $this->document($entry);
        }
        $component = Livewire::test(ListCustomFormEntries::class)->mountTableAction($path === 'modal' ? 'view_template_pdf_modal' : $action, $entry);
        $mounted = $component->instance()->getMountedAction();
        $callback = $path === 'modal'
            ? collect($mounted->getExtraModalFooterActions())->first(fn ($button): bool => $button->getName() === $action)->getActionFunction()
            : $mounted->getActionFunction();
        $stale = $mounted->getRecord();
        $data = array_replace($entry->data, ['registration_status' => 'approved', 'answer' => 'Fresh answer']);
        DB::table('custom_form_entries')->where('id', $entry->id)->update(['review_status' => 'approved', 'data' => json_encode($data)]);
        $before = $this->row($entry);
        $auditCount = $this->entryAuditCount($entry);
        $this->assertDenied(fn () => $path === 'modal' ? $callback(['review_note' => 'Stale return']) : $callback($stale, ['review_note' => 'Stale return']), 409);
        $this->assertSame($before, $this->row($entry));
        $this->assertSame($auditCount, $this->entryAuditCount($entry));
    }

    private function application(bool $configured = true): array
    {
        $form = CustomForm::query()->create(['name' => 'Review authorization application', 'slug' => 'review-auth-'.uniqid(), 'is_active' => true]);
        $workflow = null;
        if ($configured) {
            $workflow = WorkflowNotification::query()->create(['name' => 'Review authorization '.uniqid(), 'stages' => array_map(fn (string $type): array => ['type' => 'stage', 'data' => ['stage_type' => $type, 'stage_name' => ucfirst($type), 'responsible_role' => ['registrar_officer'], 'notification_channels' => []]], ['review', 'verification'])]);
            $workflow->forms()->attach($form);
        }
        $entry = CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => 'pending', 'data' => ['registration_status' => 'pending', 'answer' => 'Original answer']]);
        DocumentTemplate::query()->where('type', 'custom_form_'.$form->id)->delete();

        return [$entry->fresh(), $workflow];
    }

    private function document(CustomFormEntry $entry): void
    {
        DocumentTemplate::query()->create(['name' => 'Review authorization PDF', 'type' => 'custom_form_'.$entry->custom_form_id, 'content' => '<p>Application</p>']);
    }

    private function fakePdf(): void
    {
        $this->mock(DocumentRenderer::class, fn ($mock) => $mock->shouldReceive('render')->andReturn(new class
        {
            public function output(): string
            {
                return '%PDF-test';
            }
        }));
    }

    private function grant(User $user, array $actions): void
    {
        foreach ($actions as $action) {
            $user->givePermissionTo(Permission::findOrCreate($action.':CustomFormEntry', 'web'));
        }
    }

    private function user(string $type, string $role): User
    {
        $name = 'review-auth-'.uniqid();
        $user = User::query()->forceCreate(['name' => $name, 'username' => $name, 'email' => $name.'@example.test', 'password' => 'test-password', 'date_of_birth' => '2000-01-01', 'registration_type' => $type, 'is_active' => true]);
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    private function assertDenied(callable $callback, int $status = 403): void
    {
        try {
            $callback();
            $this->fail('Review must require current authorized access.');
        } catch (AuthorizationException $exception) {
            $this->assertSame($status, $exception->status() ?? 403);
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }

    private function row(CustomFormEntry $entry): array
    {
        return (array) DB::table('custom_form_entries')->where('id', $entry->id)->first();
    }

    private function entryAuditCount(CustomFormEntry $entry): int
    {
        return DB::table('audit_logs')->where('auditable_type', CustomFormEntry::class)->where('auditable_id', $entry->id)->count();
    }
}
