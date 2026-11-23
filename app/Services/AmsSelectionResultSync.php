<?php

namespace App\Services;

use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\ExamResults\Tables\ExamResultsTable;
use App\Models\User;
use App\Support\CandidateApplicationLevels;
use App\Support\CandidateLatinName;
use App\Support\CandidateProfilePayload;
use App\Support\CandidateTypeResolver;
use App\Support\FormEntryData;
use App\Support\MenuRecordVisibility;
use App\Support\PassedResultMenuOptions;
use App\Support\UserTypeOptions;
use Carbon\Carbon;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AmsSelectionResultSync
{
    private const ACKNOWLEDGEMENT_TABLE = 'ams_selection_result_syncs';

    public function hasAcknowledgement(CustomFormEntry $entry): bool
    {
        return $this->acknowledgement($entry) !== null;
    }

    public function sync(CustomFormEntry $entry, bool $notify = false, string $action = 'notify_all_students'): bool
    {
        abort_unless(in_array($action, ['notify_student', 'notify_all_students'], true), 403);

        // Commit the remote acknowledgement independently of local notification delivery.
        // A failed delivery can then retry without transferring the account/result again.
        DB::transaction(function () use ($entry, $action): void {
            $current = $entry->newQuery()->lockForUpdate()->findOrFail($entry->getKey());
            $acknowledgement = $this->acknowledgement($current);
            $this->authorize($current, $action, $acknowledgement);

            if ($acknowledgement !== null) {
                return;
            }

            $response = $this->sendToAms($current);
            $acknowledgement = [
                'version' => 1,
                'application_id' => (string) $current->getKey(),
                'source_account_id' => (string) $current->created_by,
                'selection_result_id' => (string) $response['selection_result_id'],
                'account_id' => (string) $response['account_id'],
                'account_status' => $response['account_status'],
                'provisional_account_id' => isset($response['provisional_account_id']) ? (string) $response['provisional_account_id'] : null,
                'profile_synced' => true,
                'profile_entry_id' => (string) $response['profile_entry_id'],
                'status' => 'synced',
                'synced_at' => now()->toIso8601String(),
            ];
            DB::table(self::ACKNOWLEDGEMENT_TABLE)->updateOrInsert([
                'custom_form_entry_id' => $current->getKey(),
            ], [
                'acknowledgement' => json_encode($acknowledgement, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $entry->refresh();
        if (! $notify) {
            return false;
        }

        $notified = DB::transaction(function () use ($entry, $action): bool {
            $current = $entry->newQuery()->lockForUpdate()->findOrFail($entry->getKey());
            $acknowledgement = $this->acknowledgement($current);
            abort_unless($acknowledgement !== null, 409);
            $this->authorize($current, $action, $acknowledgement);

            $notified = ! ExamResultsTable::hasStudentPassedNotification($current)
                && ExamResultsTable::sendPassedNotification(
                    $current,
                    PassedResultMenuOptions::EXAM_RESULTS,
                    action: $action,
                );

            // A confirm-only operation and an unsuccessful notification remain retryable.
            if ($notified || ExamResultsTable::hasStudentPassedNotification($current)) {
                MenuRecordVisibility::clearRecords(
                    $current->newQuery()->whereKey($current->getKey()),
                    ExamResultResource::HIDDEN_FLAG,
                );
            }

            return $notified;
        });
        $entry->refresh();

        return $notified;
    }

    private function authorize(CustomFormEntry $entry, string $action, ?array $acknowledgement): void
    {
        // Keep row and bulk permissions distinct, including the frozen workflow role.
        abort_unless(ExamResultsTable::canNotifyRecord($entry, action: $action), 403);
        if (ExamResultResource::getEloquentQuery()->whereKey($entry->getKey())->exists()) {
            return;
        }

        // Only this service's acknowledged, delivered result can retry after being hidden.
        abort_unless($acknowledgement !== null
            && ExamResultsTable::hasStudentPassedNotification($entry)
            && MenuRecordVisibility::includingCleared(fn (): bool => ExamResultResource::getEloquentQuery()->whereKey($entry->getKey())->exists()), 409);
    }

    private function acknowledgement(CustomFormEntry $entry): ?array
    {
        $acknowledgement = DB::table(self::ACKNOWLEDGEMENT_TABLE)
            ->where('custom_form_entry_id', $entry->getKey())
            ->value('acknowledgement');
        if (is_string($acknowledgement)) {
            $acknowledgement = json_decode($acknowledgement, true);
        }

        return is_array($acknowledgement)
            && ($acknowledgement['version'] ?? null) === 1
            && is_string($acknowledgement['synced_at'] ?? null)
            && filled($acknowledgement['synced_at'])
            && $this->validAcknowledgement($entry, $acknowledgement)
                ? $acknowledgement
                : null;
    }

    private function sendToAms(CustomFormEntry $entry): array
    {
        $baseUrl = rtrim((string) config('services.ams.base_url'), '/');
        $token = (string) config('services.ams.token');
        if (! config('services.ams.enabled') || $baseUrl === '' || $token === '') {
            throw new RuntimeException('AMS selection result integration is not configured.');
        }

        $payload = $this->payload($entry);
        Validator::make($payload, [
            'result.academic_year' => ['nullable', 'string', 'max:20'],
            'result.date_of_birth' => ['required', 'date_format:Y-m-d'],
            'result.name' => ['required_without:result.name_latin'],
            'result.name_latin' => ['required_without:result.name'],
        ], attributes: [
            'result.academic_year' => __('exam_results.academic_year'),
            'result.date_of_birth' => __('exam_results.date_of_birth'),
            'result.name' => __('exam_results.name_khmer'),
            'result.name_latin' => __('exam_results.name_latin'),
        ])->validate();

        $response = Http::acceptJson()->withToken($token)
            ->connectTimeout(2)->timeout((int) config('services.ams.timeout', 5))
            ->post($baseUrl.'/api/external/candidate/selection-results', $payload)
            ->throw()->json();

        if (! is_array($response) || ! $this->validAcknowledgement($entry, $response)) {
            throw new RuntimeException('AMS did not acknowledge both the selection result and candidate account.');
        }

        return $response;
    }

    private function validAcknowledgement(CustomFormEntry $entry, array $response): bool
    {
        return ($response['application_id'] ?? null) === (string) $entry->getKey()
            && ($response['source_account_id'] ?? null) === (string) $entry->created_by
            && ($response['status'] ?? null) === 'synced'
            && self::validRemoteId($response['selection_result_id'] ?? null)
            && self::validRemoteId($response['account_id'] ?? null)
            && in_array($response['account_status'] ?? null, ['provisional', 'enrolled'], true)
            && (! isset($response['provisional_account_id']) || self::validRemoteId($response['provisional_account_id']))
            && (($response['account_status'] ?? null) !== 'provisional' || self::validRemoteId($response['provisional_account_id'] ?? null))
            && ($response['profile_synced'] ?? false) === true
            && self::validRemoteId($response['profile_entry_id'] ?? null);
    }

    private static function validRemoteId(mixed $value): bool
    {
        return (is_string($value) && trim($value) !== '') || (is_int($value) && $value > 0);
    }

    private function payload(CustomFormEntry $entry): array
    {
        $entry->loadMissing(['creator', 'customForm']);
        $owner = $entry->creator;
        $profile = CustomFormEntry::query()->where('created_by', $entry->created_by)
            ->whereHas('customForm', fn ($query) => $query->where('slug', 'profile'))->latest('id')->first();
        $data = array_replace(is_array($profile?->data) ? $profile->data : [],
            array_filter(is_array($entry->data) ? $entry->data : [], fn ($value) => filled($value)));
        $firstKh = self::text(FormEntryData::firstFilled($data, ['first_name_kh', 'first_name_khmer', 'surname_kh'], $owner?->first_name_kh));
        $lastKh = self::text(FormEntryData::firstFilled($data, ['last_name_kh', 'last_name_khmer', 'given_name_kh'], $owner?->last_name_kh));
        $firstEn = self::text(FormEntryData::firstFilled($data, ['first_name_en', 'first_name_latin'], $owner?->first_name_en));
        $lastEn = self::text(FormEntryData::firstFilled($data, ['last_name_en', 'last_name_latin'], $owner?->last_name_en));
        if ($firstEn === null && $lastEn === null) {
            [$firstEn, $lastEn] = CandidateLatinName::split(self::text(FormEntryData::firstFilled($data, ['name_latin', 'full_name_en', 'name_en'], $owner?->name_latin)));
        }
        $degree = self::text(FormEntryData::firstFilled($data, ['degree_level', 'selected_degree_level', 'academic_level']));
        $degree ??= UserTypeOptions::findByKey(CandidateTypeResolver::forEntry($entry) ?? '')?->group_name;

        return [
            'application_id' => (string) $entry->getKey(),
            'account' => $this->encryptedAccount($entry),
            'profile' => CandidateProfilePayload::make($data, [
                'name_khmer' => $owner?->name, 'name_latin' => $owner?->name_latin,
                'email' => $owner?->email, 'phone_number' => $owner?->phone,
                'date_of_birth' => self::date($owner?->date_of_birth),
            ]),
            'result' => [
                'name' => trim(($firstKh ?? '').' '.($lastKh ?? '')) ?: self::text(FormEntryData::firstFilled($data, ['name_khmer', 'full_name_kh', 'name_kh'], $owner?->name)),
                'name_latin' => strtoupper(trim(($firstEn ?? '').' '.($lastEn ?? ''))),
                'first_name_kh' => $firstKh,
                'last_name_kh' => $lastKh,
                'first_name_en' => $firstEn,
                'last_name_en' => $lastEn,
                'gender' => self::text(FormEntryData::firstFilled($data, ['gender', 'sex'], $owner?->gender)),
                'academic_year' => self::text(FormEntryData::academicYearLabel($data, $owner?->academic_year ?? '')),
                'seat_number' => self::text(FormEntryData::firstFilled($data, ['seat_number', 'exam_seat_number'], $entry->candidate_id)),
                'national_exam_pass_list_number' => self::text($data['national_exam_pass_list_number'] ?? null),
                'date_of_birth' => self::date(FormEntryData::firstFilled($data, ['date_of_birth', 'dob', 'birth_date'], $owner?->date_of_birth)),
                'exam_date' => self::date($data['exam_date'] ?? null),
                'degree_level' => $degree,
                'program' => self::text(FormEntryData::majorLabel($data, '')),
            ],
        ];
    }

    private function encryptedAccount(CustomFormEntry $entry): string
    {
        $owner = $entry->creator;
        if (! $owner instanceof User || blank($owner->username) || ! Hash::isHashed($owner->getRawOriginal('password'))) {
            throw ValidationException::withMessages(['account' => __('exam_results.ams_sync_account_missing')]);
        }
        $linked = $owner->linkedSystemUser();
        [$first, $last] = CandidateLatinName::split($linked?->name ?: $owner->name_latin ?: $owner->name);
        $account = [
            'version' => 1,
            'source_account_id' => (string) $owner->id,
            'username' => (string) $owner->username,
            'email' => self::text($owner->email),
            'phone' => self::text($owner->phone),
            'password_hash' => $owner->getRawOriginal('password'),
            'first_name_latin' => $first,
            'last_name_latin' => $last,
            'candidate_type' => CandidateApplicationLevels::currentType($owner) ?? CandidateTypeResolver::forEntry($entry),
            'is_active' => (bool) $owner->is_active,
        ];
        $key = (string) config('services.ams.account_key');
        if ($key === '') {
            throw new RuntimeException('AMS account credential encryption is not configured.');
        }
        $encrypter = new Encrypter(hash('sha256', 'uhs-selection-account-v1|'.$key, true), 'AES-256-CBC');

        return $encrypter->encryptString(json_encode($account, JSON_THROW_ON_ERROR));
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' && $value !== '-' ? trim((string) $value) : null;
    }

    private static function date(mixed $value): ?string
    {
        if (blank($value) || $value === '-') {
            return null;
        }
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}
