<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProtectedFileAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_can_access_own_payment_slip(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('payment-slips/own.png', 'image-data');

        $candidate = $this->createCandidate('candidate-one');
        $payment = $this->createPayment($candidate, 'OWN-001');

        $this->actingAs($candidate)
            ->get(route('protected.payment-slip', $payment))
            ->assertOk();
    }

    public function test_candidate_cannot_access_another_candidates_payment_slip(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('payment-slips/other.png', 'image-data');

        $owner = $this->createCandidate('candidate-owner');
        $otherCandidate = $this->createCandidate('candidate-other');
        $payment = $this->createPayment($owner, 'OTHER-001', 'payment-slips/other.png');

        $this->actingAs($otherCandidate)
            ->get(route('protected.payment-slip', $payment))
            ->assertForbidden();
    }

    private function createCandidate(string $username): User
    {
        return User::query()->create([
            'registration_type' => 'student',
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }

    private function createPayment(User $candidate, string $receipt, string $path = 'payment-slips/own.png'): Payment
    {
        return Payment::query()->create([
            'users_id' => $candidate->getKey(),
            'receipt_number' => $receipt,
            'payment_slip_path' => $path,
            'amount_kh' => '1000.00',
            'status_payt' => 'paid',
        ]);
    }
}
