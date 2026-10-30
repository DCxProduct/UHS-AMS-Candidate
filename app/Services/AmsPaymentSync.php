<?php

namespace App\Services;

use App\Enums\WorkflowPaymentSource;
use App\Models\AmsPaymentRequest;
use App\Models\Payment;
use App\Models\WorkflowNotification;
use App\Support\CandidateDisplayName;
use App\Support\FormEntryData;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

class AmsPaymentSync
{
    public static function usesAms(CustomFormEntry $entry): bool
    {
        return filled($entry->custom_form_id)
            && WorkflowNotification::forEntry($entry)?->paymentSource() === WorkflowPaymentSource::Ams;
    }

    public function sync(CustomFormEntry $entry): ?AmsPaymentRequest
    {
        $entry->refresh();

        if (! self::usesAms($entry)
            || (WorkflowStageProgression::enabled($entry)
                ? ! WorkflowStageProgression::isCurrent($entry, \App\Enums\WorkflowStageType::Payment)
                : ! in_array(strtolower((string) $entry->review_status), ['approved', 'accepted'], true))
            || ! WorkflowStageMessages::requiresPayment($entry)
            || blank($entry->created_by)) {
            return null;
        }

        // Never send an already collected local application for a second collection.
        if (Payment::query()->where('custom_form_entry_id', $entry->getKey())->where('status_payt', 'paid')->exists()) {
            return AmsPaymentRequest::query()->where('custom_form_entry_id', $entry->getKey())->first();
        }

        $request = AmsPaymentRequest::query()->firstOrCreate(['custom_form_entry_id' => $entry->getKey()]);

        if ($request->sync_status === 'synced' && filled($request->ams_request_id)) {
            return $request;
        }

        $payload = $this->payload($entry);
        $request->forceFill(['payload' => $payload, 'sync_status' => 'pending', 'attempts' => $request->attempts + 1])->save();

        try {
            $baseUrl = rtrim((string) config('services.ams.base_url'), '/');
            $token = (string) config('services.ams.token');

            if (! config('services.ams.enabled') || $baseUrl === '' || $token === '') {
                throw new RuntimeException('AMS payment integration is not configured.');
            }

            $data = Http::acceptJson()->withToken($token)
                ->connectTimeout(2)->timeout((int) config('services.ams.timeout', 5))
                ->post($baseUrl.'/api/external/candidate/other-fees', $payload)
                ->throw()->json();
            Validator::make(is_array($data) ? $data : [], [
                'application_id' => ['required'], 'ams_request_id' => ['required'],
                'payment_status' => ['required', 'in:paid,unpaid'],
            ])->validate();

            if ((string) $data['application_id'] !== (string) $entry->getKey()
                || ! is_scalar($data['ams_request_id']) || strlen((string) $data['ams_request_id']) > 100) {
                throw new RuntimeException('AMS returned a different application reference.');
            }

            $request->forceFill(['ams_request_id' => (string) $data['ams_request_id'], 'sync_status' => 'synced', 'last_error' => null, 'synced_at' => now()])->save();
        } catch (Throwable $exception) {
            // A failed integration never rolls back the existing Checked action.
            $request->forceFill(['sync_status' => 'failed', 'last_error' => 'AMS synchronization failed. Retry with ams:sync-payments.'])->save();
            report($exception);
        }

        return $request->fresh();
    }

    private function payload(CustomFormEntry $entry): array
    {
        $entry->loadMissing('customForm');
        $owner = \App\Models\User::query()->find($entry->created_by);
        $profile = CustomFormEntry::query()->where('created_by', $entry->created_by)
            ->whereHas('customForm', fn ($query) => $query->where('slug', 'profile'))->latest('id')->first();
        $data = array_replace(is_array($profile?->data) ? $profile->data : [], is_array($entry->data) ? $entry->data : []);
        $nameKh = trim(implode(' ', array_filter([
            self::text(FormEntryData::firstFilled($data, ['first_name_kh', 'first_name_khmer', 'surname_kh'], $owner?->first_name_kh)),
            self::text(FormEntryData::firstFilled($data, ['last_name_kh', 'last_name_khmer', 'given_name_kh'], $owner?->last_name_kh)),
        ])));
        $nameEn = FormEntryData::firstFilled($data, ['full_name_en', 'name_en', 'name_latin']);
        $nameEn = $nameEn ?: trim(implode(' ', array_filter([self::text($data['first_name_en'] ?? null), self::text($data['last_name_en'] ?? null)])));
        $dob = FormEntryData::firstFilled($data, ['date_of_birth', 'dob', 'birth_date'], $owner?->date_of_birth);
        try {
            $dob = filled($dob) ? \Illuminate\Support\Carbon::parse($dob)->format('Y-m-d') : null;
        } catch (Throwable) {
            $dob = null;
        }

        return [
            'application_id' => (string) $entry->getKey(),
            'candidate_identifier' => filled($entry->candidate_id) ? (string) $entry->candidate_id : null,
            'form_id' => (string) $entry->custom_form_id,
            'form_name' => (string) ($entry->customForm?->display_name ?: $entry->customForm?->name),
            'candidate' => [
                'name_kh' => $nameKh ?: self::text(FormEntryData::firstFilled($data, ['full_name_kh', 'name_kh'], $owner?->name)),
                'name_en' => self::text($nameEn) ?: CandidateDisplayName::for($owner),
                'gender' => self::text(FormEntryData::firstFilled($data, ['gender', 'sex'], $owner?->gender)),
                'dob' => $dob,
                'phone' => self::text(FormEntryData::firstFilled($data, ['phone', 'phone_number', 'contact_number'], $owner?->phone)),
            ],
            'academic_profile' => [
                'major' => FormEntryData::majorLabel($data, ''),
                'academic_year' => FormEntryData::academicYearLabel($data, ''),
                'degree_level' => self::text(FormEntryData::firstFilled($data, ['degree_level', 'academic_level'])),
                'generation' => self::text(FormEntryData::firstFilled($data, ['generation'])),
                'year_level' => self::text(FormEntryData::firstFilled($data, ['year_level', 'year'])),
            ],
        ];
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
