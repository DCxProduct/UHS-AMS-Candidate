<?php

namespace App\Support;

use App\Models\GeoLocation;
use Illuminate\Support\Facades\Schema;

final class CandidateProfilePayload
{
    public static function make(array $data, array $fallback): array
    {
        // A blank application answer must not erase the candidate's stored profile.
        $data = array_replace($fallback, array_filter($data, fn ($value) => filled($value)));
        foreach (['name_khmer' => ['name', 'full_name_kh', 'name_kh'],
            'name_latin' => ['full_name_en', 'name_en'], 'phone_number' => ['phone', 'student_phone'],
            'email' => ['email_address', 'student_email'], 'date_of_birth' => ['dob', 'birth_date']] as $key => $aliases) {
            foreach ($aliases as $alias) {
                if (blank($data[$key] ?? null) && filled($data[$alias] ?? null)) {
                    $data[$key] = $data[$alias];
                }
            }
        }
        foreach (['name_khmer' => ['first_name_kh', 'last_name_kh'], 'name_latin' => ['first_name_en', 'last_name_en']] as $field => $parts) {
            if (blank($data[$field] ?? null)) {
                $data[$field] = trim(implode(' ', array_map(fn ($part) => is_scalar($data[$part] ?? null) ? $data[$part] : '', $parts)));
            }
        }
        $fields = DynamicProfileSchema::fields();
        $values = DynamicProfileSchema::filter($data, $fields);
        $locations = [];
        foreach ($fields as $field => $configuration) {
            $type = $configuration['options']['geo_location_type'] ?? null;
            if (! in_array($type, ['province', 'district', 'commune', 'village'], true)) {
                continue;
            }
            unset($values[$field]);
            $value = $data[$field] ?? null;
            if (! is_scalar($value) || blank($value) || ! Schema::hasTable('geo_locations')) {
                continue;
            }
            $query = GeoLocation::query()->where('type', $type);
            $location = ($configuration['options']['geo_location_value_column'] ?? 'id') === 'code'
                ? $query->where('code', (string) $value)->first()
                : $query->find($value);
            if ($location) {
                $locations[$field] = (string) $location->code;
            }
        }
        return ['version' => 1, 'data' => $values, 'locations' => $locations];
    }
}