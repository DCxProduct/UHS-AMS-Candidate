<?php

namespace App\Services;

use App\Models\AccountUser;
use App\Models\AmsExcelAccountReceipt;
use App\Models\GeoLocation;
use App\Models\NewStudentAccount;
use App\Models\SystemUser;
use App\Support\AccountContacts;
use App\Support\CandidateProfilePayload;
use App\Support\DynamicProfileSchema;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class AmsExcelStudentAccountImport
{
    public function receive(array $data): AmsExcelAccountReceipt
    {
        return DB::transaction(function () use ($data): AmsExcelAccountReceipt {
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(723611260002)');
            }

            $receipt = AmsExcelAccountReceipt::query()->where('source_system', $data['source_system'])
                ->where('source_user_id', $data['source_user_id'])->lockForUpdate()->first();
            if ($receipt !== null) {
                if ($receipt->student_id !== trim($data['student_id'])) {
                    throw new DomainException;
                }
                // Retried deliveries must not reset a password, reactivate an
                // archived account, or overwrite subsequent Candidate edits.
                return $receipt;
            }

            $data['student_id'] = trim($data['student_id']);
            $data['email'] = AccountContacts::email($data['email']);
            $data['phone'] = AccountContacts::phone($data['phone']);
            if (NewStudentAccount::query()->where('student_id', $data['student_id'])->exists()
                || AccountContacts::matching(AccountUser::withTrashed(), $data)->exists()
                || AccountContacts::matching(SystemUser::query(), $data)->exists()
                || AccountContacts::matching(NewStudentAccount::query(), $data)->exists()) {
                throw new DomainException;
            }

            $form = DB::table('custom_forms')->where('slug', 'profile')->whereNull('deleted_at')->first();
            abort_unless($form, 503, 'Candidate profile form is not configured.');
            $fields = DynamicProfileSchema::fields($form->id);
            abort_unless($fields, 503, 'Candidate profile fields are not configured.');
            $profile = DynamicProfileSchema::filter(
                CandidateProfilePayload::normalizeChoices($data['profile']['data'], $fields), $fields,
            );
            foreach ($fields as $name => $field) {
                $type = $field['options']['geo_location_type'] ?? null;
                if (! in_array($type, ['province', 'district', 'commune', 'village'], true)) {
                    continue;
                }
                unset($profile[$name]);
                $code = $data['profile']['locations'][$name] ?? null;
                if ($code !== null) {
                    $location = GeoLocation::query()->where('type', $type)->where('code', $code)->first();
                    if ($location !== null) {
                        $profile[$name] = ($field['options']['geo_location_value_column'] ?? 'id') === 'code'
                            ? (string) $location->code : (string) $location->id;
                    }
                }
            }
            Validator::make(['data' => $profile], DynamicProfileSchema::rules($fields, 'data'))->validate();

            $account = new NewStudentAccount;
            $account->forceFill([
                'student_id' => $data['student_id'], 'email' => $data['email'], 'phone' => $data['phone'],
                'first_name_latin' => $data['first_name_latin'], 'last_name_latin' => $data['last_name_latin'],
                'name' => $data['name_khmer'] ?? null, 'password' => $data['password_hash'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'first_name_kh' => $profile['first_name_kh'] ?? null,
                'last_name_kh' => $profile['last_name_kh'] ?? null,
                'academic_year' => $profile['academic_year'] ?? null,
            ])->save();
            $profileId = DB::table('custom_form_entries')->insertGetId([
                'custom_form_id' => $form->id, 'created_by' => $account->auth_id,
                'data' => json_encode($profile, JSON_THROW_ON_ERROR),
                'review_status' => DynamicProfileSchema::complete($profile, $fields) ? 'approved' : 'pending',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return AmsExcelAccountReceipt::query()->create([
                'source_system' => $data['source_system'], 'source_user_id' => $data['source_user_id'],
                'student_id' => $data['student_id'], 'new_student_account_id' => $account->id,
                'profile_entry_id' => $profileId,
            ]);
        }, attempts: 3);
    }
}
