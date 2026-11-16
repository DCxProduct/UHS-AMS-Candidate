<?php

namespace App\Services;

use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\ExamResults\Tables\ExamResultsTable;
use App\Models\User;
use App\Support\CandidateApplicationLevels;
use App\Support\CandidateLatinName;
use App\Support\CandidateTypeResolver;
use App\Support\FormEntryData;
use App\Support\MenuRecordVisibility;
use App\Support\PassedResultMenuOptions;
use App\Support\UserTypeOptions;
use Carbon\Carbon;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AmsSelectionResultSync
{
    public function sync(CustomFormEntry $entry, bool $notify = false): bool
    {
        $entry->refresh();
        abort_unless(ExamResultResource::getEloquentQuery()->whereKey($entry->getKey())->exists(), 409);
        // Use the existing results-release authorization, including frozen workflow roles.
        abort_unless(ExamResultsTable::canNotifyRecord($entry, action: 'notify_all_students'), 403);

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

        if (! is_array($response)
            || ($response['application_id'] ?? null) !== (string) $entry->getKey()
            || ($response['status'] ?? null) !== 'synced'
            || ! is_scalar($response['selection_result_id'] ?? null)
            || blank($response['selection_result_id'])
            || ($response['source_account_id'] ?? null) !== (string) $entry->created_by
            || ! is_scalar($response['account_id'] ?? null)
            || blank($response['account_id'])
            || ! in_array($response['account_status'] ?? null, ['provisional', 'enrolled'], true)
            || (($response['account_status'] ?? null) === 'provisional' && blank($response['provisional_account_id'] ?? null))) {
            throw new RuntimeException('AMS did not acknowledge both the selection result and candidate account.');
        }

        // Candidate notifications stay in Candidate and are released only after AMS acknowledges.
        $notified = $notify && ExamResultsTable::sendPassedNotification(
            $entry,
            PassedResultMenuOptions::EXAM_RESULTS,
            action: 'notify_all_students',
        );

        MenuRecordVisibility::clearRecords(
            $entry->newQuery()->whereKey($entry->getKey()),
            ExamResultResource::HIDDEN_FLAG,
        );

        return $notified;
    }

    private function payload(CustomFormEntry $entry): array
    {
        $entry->loadMissing(['creator', 'customForm']);
        $owner = $entry->creator;
        $profile = CustomFormEntry::query()->where('created_by', $entry->created_by)
            ->whereHas('customForm', fn ($query) => $query->where('slug', 'profile'))->latest('id')->first();
        $data = array_replace(is_array($profile?->data) ? $profile->data : [], is_array($entry->data) ? $entry->data : []);
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
