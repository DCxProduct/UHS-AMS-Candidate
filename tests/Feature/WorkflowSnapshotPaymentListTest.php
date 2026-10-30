<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;
use App\Models\PaymentStatusList;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function workflow(CustomForm $form, bool $requiresPayment): WorkflowNotification
    {
        $workflow = WorkflowNotification::query()->create([
            'name' => 'Workflow '.$form->slug,
            'stages' => $this->stages($requiresPayment),
        ]);
        $workflow->forms()->attach($form);

        return $workflow;
    }

    private function stages(bool $requiresPayment): array
    {
        $types = $requiresPayment ? ['review', 'payment', 'awaiting_results'] : ['review', 'awaiting_results'];

        return array_map(fn (string $type): array => [
            'type' => 'stage',
            'data' => ['stage_name' => $type, 'stage_type' => $type, 'responsible_role' => ['admin']],
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
