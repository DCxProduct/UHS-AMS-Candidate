<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;
use App\Models\AmsPaymentRequest;
use App\Models\PaymentStatusList;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkflowSnapshotPaymentListTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        Http::fake();
        Mail::fake();
        $admin = $this->user('snapshot_payment_admin', 'admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
        $this->candidate = $this->user('snapshot_payment_candidate', 'student');
    }

    public function test_removing_a_payment_stage_keeps_existing_applications_payable_but_excludes_new_ones(): void
    {
        $form = $this->form('removed-payment');
        $workflow = $this->workflow($form, true);
        $existing = $this->entry($form);

        $workflow->update(['stages' => $this->stages(false)]);
        $new = $this->entry($form);

        $this->assertSame([$existing->getKey()], PaymentStatusListResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$existing->getKey()], PaymentStatusListResource::getDashboardEloquentQuery()->pluck('id')->all());
        $this->assertTrue(data_get($existing->fresh()->workflow_snapshot, 'requires_payment'));
        $this->assertFalse(data_get($new->fresh()->workflow_snapshot, 'requires_payment'));

        $workflow->delete();
        $this->assertSame([$existing->getKey()], PaymentStatusListResource::getEloquentQuery()->pluck('id')->all());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_adding_a_payment_stage_applies_only_to_new_applications_even_when_the_form_payment_toggle_is_enabled(): void
    {
        $form = $this->form('added-payment', true);
        $workflow = $this->workflow($form, false);
        $existing = $this->entry($form);

        $workflow->update(['stages' => $this->stages(true)]);
        $new = $this->entry($form);

        $this->assertSame([$new->getKey()], PaymentStatusListResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$new->getKey()], PaymentStatusListResource::getUnpaidEloquentQuery()->pluck('id')->all());
        $this->assertFalse(data_get($existing->fresh()->workflow_snapshot, 'requires_payment'));
        $this->assertTrue(data_get($new->fresh()->workflow_snapshot, 'requires_payment'));
    }

    public function test_existing_static_applications_keep_the_static_payment_toggle_after_a_template_is_attached(): void
    {
        $payableForm = $this->form('static-payable', true);
        $payableStatic = $this->entry($payableForm);
        $this->workflow($payableForm, false);
        $newWithoutPayment = $this->entry($payableForm);

        $freeForm = $this->form('static-free');
        $freeStatic = $this->entry($freeForm);
        $this->workflow($freeForm, true);
        $newWithPayment = $this->entry($freeForm);

        $this->assertEqualsCanonicalizing(
            [$payableStatic->getKey(), $newWithPayment->getKey()],
            PaymentStatusListResource::getEloquentQuery()->pluck('id')->all(),
        );
        foreach ([$payableStatic, $freeStatic] as $entry) {
            $this->assertFalse(data_get($entry->fresh()->workflow_snapshot, 'has_workflow'));
        }
        foreach ([$newWithoutPayment, $newWithPayment] as $entry) {
            $this->assertTrue(data_get($entry->fresh()->workflow_snapshot, 'has_workflow'));
        }

        // Keep the existing static form setting behavior independent of template edits.
        $payableForm->update(['requires_payment' => false]);
        $freeForm->update(['requires_payment' => true]);
        $this->assertEqualsCanonicalizing(
            [$freeStatic->getKey(), $newWithPayment->getKey()],
            PaymentStatusListResource::getEloquentQuery()->pluck('id')->all(),
        );
    }

    public function test_ams_applications_are_excluded_before_sync_and_after_payment_from_all_cashier_application_queries(): void
    {
        $form = $this->form('ams-only-payments');
        $this->workflow($form, true, 'ams');
        $unpaid = $this->entry($form);
        $paid = $this->entry($form);
        AmsPaymentRequest::query()->create([
            'custom_form_entry_id' => $paid->getKey(),
            'payment_status' => 'paid',
            'external_payment_id' => 'ams-only-payment',
        ]);

        $localForm = $this->form('local-payments');
        $this->workflow($localForm, true, 'system');
        $local = $this->entry($localForm);
        $static = $this->entry($this->form('static-payments', true));

        $this->assertCashierApplicationIds([$local->getKey(), $static->getKey()]);
        $this->assertDatabaseMissing('ams_payment_requests', ['custom_form_entry_id' => $unpaid->getKey()]);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('custom_form_entries', ['id' => $unpaid->getKey()]);
        $this->assertDatabaseHas('custom_form_entries', ['id' => $paid->getKey()]);
    }

    public function test_payment_source_edits_apply_to_new_applications_without_rerouting_frozen_applications(): void
    {
        $localForm = $this->form('local-changed-to-ams');
        $localWorkflow = $this->workflow($localForm, true, 'system');
        $existingLocal = $this->entry($localForm);
        $localWorkflow->update(['stages' => $this->stages(true, 'ams')]);
        $this->entry($localForm);

        $amsForm = $this->form('ams-changed-to-local');
        $amsWorkflow = $this->workflow($amsForm, true, 'ams');
        $this->entry($amsForm);
        $amsWorkflow->update(['stages' => $this->stages(true, 'system')]);
        $newLocal = $this->entry($amsForm);

        $staticForm = $this->form('static-with-new-ams-template', true);
        $existingStatic = $this->entry($staticForm);
        $this->workflow($staticForm, true, 'ams');
        $this->entry($staticForm);

        $this->assertCashierApplicationIds([$existingLocal->getKey(), $newLocal->getKey(), $existingStatic->getKey()]);
    }

    public function test_flat_ams_snapshots_and_legacy_entries_without_snapshots_use_the_correct_payment_source(): void
    {
        $amsForm = $this->form('flat-ams-snapshot');
        $this->workflow($amsForm, true, 'ams');
        $flat = $this->entry($amsForm);
        $snapshot = $flat->fresh()->workflow_snapshot;
        $snapshot['stages'] = array_column($snapshot['stages'], 'data');
        DB::table('custom_form_entries')->where('id', $flat->getKey())->update(['workflow_snapshot' => json_encode($snapshot)]);
        $legacyAms = $this->entry($amsForm);
        DB::table('custom_form_entries')->where('id', $legacyAms->getKey())->update(['workflow_snapshot' => null]);

        $localForm = $this->form('legacy-local-snapshot');
        $this->workflow($localForm, true, 'system');
        $legacyLocal = $this->entry($localForm);
        DB::table('custom_form_entries')->where('id', $legacyLocal->getKey())->update(['workflow_snapshot' => null]);

        $this->assertCashierApplicationIds([$legacyLocal->getKey()]);
    }

    private function assertCashierApplicationIds(array $expected): void
    {
        foreach ([
            PaymentStatusListResource::getEloquentQuery(),
            PaymentStatusListResource::getDashboardEloquentQuery(),
            PaymentStatusListResource::getUnpaidEloquentQuery(),
            PaymentStatusListResource::getUnpaidEloquentQuery(includeCleared: true),
        ] as $query) {
            $this->assertEqualsCanonicalizing($expected, $query->pluck('id')->all());
        }
    }

    private function workflow(CustomForm $form, bool $requiresPayment, string $paymentSource = 'system'): WorkflowNotification
    {
        $workflow = WorkflowNotification::query()->create([
            'name' => 'Workflow '.$form->slug,
            'stages' => $this->stages($requiresPayment, $paymentSource),
        ]);
        $workflow->forms()->attach($form);

        return $workflow;
    }

    private function stages(bool $requiresPayment, string $paymentSource = 'system'): array
    {
        $types = $requiresPayment ? ['review', 'payment', 'awaiting_results'] : ['review', 'awaiting_results'];

        return array_map(fn (string $type): array => [
            'type' => 'stage',
            'data' => ['stage_name' => $type, 'stage_type' => $type, 'responsible_role' => ['admin'], ...($type === 'payment' ? ['payment_source' => $paymentSource] : [])],
        ], $types);
    }

    private function form(string $slug, bool $requiresPayment = false): CustomForm
    {
        return CustomForm::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'is_active' => true,
            'requires_payment' => $requiresPayment,
        ]);
    }

    private function entry(CustomForm $form): PaymentStatusList
    {
        return PaymentStatusList::query()->forceCreate([
            'custom_form_id' => $form->getKey(),
            'created_by' => $this->candidate->getKey(),
            'review_status' => 'approved',
            'data' => ['registration_status' => 'approved'],
        ]);
    }

    private function user(string $username, string $type): User
    {
        return User::query()->forceCreate([
            'registration_type' => $type,
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
