<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Payments\Pages\EditPayment;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Role;
use App\Models\SystemUser;
use App\Models\User;
use App\Services\CandidatePaymentRecorder;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentTimestampTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 10, 27)->setTime(15, 7, 23));
    }

    public function test_standalone_date_only_payment_captures_the_recording_clock(): void
    {
        $payment = app(CandidatePaymentRecorder::class)->record([
            'receipt_number' => 'TIMESTAMP-TODAY',
            'datetime_pay' => '2026-10-27',
            'amount_kh' => 4100,
        ]);

        $this->assertSame('2026-10-27 15:07:23', $payment->fresh()->datetime_pay->format('Y-m-d H:i:s'));
    }

    public function test_date_only_payment_preserves_the_selected_calendar_date(): void
    {
        $payment = app(CandidatePaymentRecorder::class)->record([
            'receipt_number' => 'TIMESTAMP-BACKDATED',
            'datetime_pay' => '2026-10-25',
            'amount_kh' => 4100,
        ]);

        $this->assertSame('2026-10-25 15:07:23', $payment->fresh()->datetime_pay->format('Y-m-d H:i:s'));
    }

    public function test_explicit_payment_timestamps_including_midnight_are_preserved(): void
    {
        foreach (['2026-10-25 08:12:34', '2026-10-25 00:00:00'] as $index => $paidAt) {
            $payment = app(CandidatePaymentRecorder::class)->record([
                'receipt_number' => 'TIMESTAMP-EXPLICIT-'.$index,
                'datetime_pay' => $paidAt,
                'amount_kh' => 4100,
            ]);

            $this->assertSame($paidAt, $payment->fresh()->datetime_pay->format('Y-m-d H:i:s'));
        }
    }

    public function test_linked_payment_captures_time_and_retains_duplicate_payment_protection(): void
    {
        $candidate = $this->user('timestamp_candidate', 'candidate');
        $this->actingAs($this->user('timestamp_cashier', 'cashier_manager'));
        $form = CustomForm::query()->create([
            'name' => 'Timestamp application', 'slug' => 'timestamp-application',
            'is_active' => true, 'requires_payment' => true,
        ]);
        $entry = CustomFormEntry::query()->create([
            'custom_form_id' => $form->getKey(), 'created_by' => $candidate->getKey(),
            'review_status' => 'approved', 'data' => ['registration_status' => 'approved'],
        ]);
        $data = [
            'custom_form_entry_id' => $entry->getKey(),
            'users_id' => $candidate->getKey(), 'form_id' => $form->getKey(),
            'receipt_number' => 'TIMESTAMP-LINKED', 'datetime_pay' => '2026-10-27',
            'amount_kh' => 4100,
        ];

        $payment = app(CandidatePaymentRecorder::class)->record($data);
        $this->assertSame('2026-10-27 15:07:23', $payment->fresh()->datetime_pay->format('Y-m-d H:i:s'));
        $this->assertSame($candidate->getKey(), $payment->users_id);

        try {
            app(CandidatePaymentRecorder::class)->record([...$data, 'receipt_number' => 'TIMESTAMP-DUPLICATE']);
            $this->fail('A duplicate application payment was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('receipt_number', $exception->errors());
        }

        $this->assertSame(1, Payment::query()->where('custom_form_entry_id', $entry->getKey())->count());
    }

    public function test_editing_amounts_does_not_truncate_the_payment_timestamp(): void
    {
        $candidate = $this->user('timestamp_edit_candidate', 'candidate');
        SystemUser::query()->forceCreate([
            'name' => $candidate->name, 'username' => $candidate->username,
            'email' => $candidate->email, 'roles' => ['candidate'],
            'password' => 'password', 'is_active' => true,
        ]);
        $admin = $this->user('timestamp_edit_admin', 'admin');
        foreach (['ViewAny', 'View', 'Update'] as $action) {
            $admin->givePermissionTo(Permission::findOrCreate($action.':Payment', 'web'));
        }
        $this->actingAs($admin);
        PaymentType::query()->create([
            'key' => 'cash', 'name_en' => 'Cash', 'method' => 'cash', 'is_active' => true,
        ]);
        $payment = Payment::query()->create([
            'users_id' => $candidate->getKey(), 'receipt_number' => 'TIMESTAMP-EDIT',
            'type_payment' => 'cash', 'datetime_pay' => '2026-10-25 13:14:15',
            'amount_kh' => 4100,
        ]);

        Livewire::test(EditPayment::class, ['record' => $payment->getRouteKey()])
            ->assertSuccessful()
            ->fillForm(['amount_kh' => '8200'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('8200.00', $payment->fresh()->amount_kh);
        $this->assertSame('2026-10-25 13:14:15', $payment->fresh()->datetime_pay->format('Y-m-d H:i:s'));
    }

    private function user(string $username, string $role): User
    {
        $user = User::query()->create([
            'registration_type' => $role === 'candidate' ? 'student' : 'admin',
            'name' => $username, 'username' => $username,
            'email' => $username.'@example.test', 'date_of_birth' => '2000-01-01',
            'password' => 'password', 'is_active' => true,
        ]);
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }
}
