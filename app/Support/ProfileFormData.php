<?php

namespace App\Support;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentCustomForms\Models\CustomFormField;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProfileFormData
{
    private const CONTAINER_TYPES = [
        'step',
        'section',
        'grid',
        'fieldset',
        'repeater',
        'wizard',
        'container',
        'group',
    ];

    private const SELECT_TYPES = [
        'select',
        'select_dropdown',
        'radio',
        'checkbox_list',
        'multi_select',
    ];

    public function prefillDataForForm(int|string|null $customFormId, array $data): array
    {
        return data_get(
            $this->prefillStateForForm($customFormId, ['data' => $data]),
            'data',
            []
        );
    }

    public function prefillStateForForm(int|string|null $customFormId, array $state, string $dataPath = 'data'): array
    {
        $customFormId = (int) $customFormId;

        if (
            $customFormId <= 0
            || ! Auth::check()
            || ! Schema::hasTable('custom_forms')
            || ! Schema::hasTable('custom_form_entries')
            || ! Schema::hasTable('custom_form_fields')
        ) {
            return $state;
        }

        $currentForm = CustomForm::query()->find($customFormId);

        if (! $currentForm || (string) $currentForm->slug === 'profile') {
            return $state;
        }

        $data = data_get($state, $dataPath, []);

        if (! is_array($data)) {
            $data = [];
        }

        $targetFields = $this->fieldsForForms($this->targetFormIds($customFormId));

        if ($targetFields->isEmpty()) {
            return $state;
        }

        // Prefill from authenticated user model
        $user = Auth::user();
        if ($user) {
            foreach ($targetFields as $targetField) {
                $targetName = trim((string) ($targetField->name ?? ''));
                
                if ($targetName === '') {
                    continue;
                }

                if (in_array($targetName, ['phone', 'phone_number', 'phone_no']) && !filled(data_get($data, $targetName)) && filled($user->phone)) {
                    data_set($data, $targetName, $user->phone);
                }
                if (in_array($targetName, ['email', 'email_address']) && !filled(data_get($data, $targetName)) && filled($user->email)) {
                    data_set($data, $targetName, $user->email);
                }
                if (in_array($targetName, ['name', 'full_name', 'student_name']) && !filled(data_get($data, $targetName)) && filled($user->name)) {
                    data_set($data, $targetName, $user->name);
                }
            }
        }

        $profileFormId = $this->profileFormId();

        if ($profileFormId && $profileFormId !== $customFormId) {
            $profileData = $this->latestProfileData($profileFormId);

            if (! empty($profileData)) {
                $profileFields = $this->fieldsForForms([$profileFormId])->keyBy('name');

                foreach ($targetFields as $targetField) {
                    $targetName = trim((string) ($targetField->name ?? ''));
                    $targetType = $this->normalizeType($targetField->type ?? '');

                    if ($targetName === '' || in_array($targetType, self::CONTAINER_TYPES, true) || $targetType === 'info') {
                        continue;
                    }

                    if (filled(data_get($data, $targetName))) {
                        continue;
                    }

                    $targetOptions = $this->normalizeOptions($targetField->options ?? []);
                    $profileKey = trim((string) (
                        $targetOptions['profile_keyword']
                        ?? $targetOptions['profile_field']
                        ?? $targetOptions['profile_data_key']
                        ?? $targetName
                    ));

                    if ($profileKey === '') {
                        continue;
                    }

                    $profileValue = data_get($profileData, $profileKey);

                    if (! filled($profileValue)) {
                        continue;
                    }

                    $profileField = $profileFields->get($profileKey);

                    data_set(
                        $data,
                        $targetName,
                        $this->resolveProfileValue($profileValue, $profileField, $targetField)
                    );
                }
            }
        }

        data_set($state, 'custom_form_id', $customFormId);
        data_set($state, $dataPath, $data);

        return $state;
    }

    public function profileKeywordOptions(array $exceptNames = []): array
    {
        $profileFormId = $this->profileFormId();

        if (! $profileFormId) {
            return [];
        }

        $exceptNames = collect($exceptNames)
            ->map(fn ($name): string => (string) $name)
            ->filter()
            ->values()
            ->all();

        return CustomFormField::query()
            ->where('custom_form_id', $profileFormId)
            ->whereNotIn('type', self::CONTAINER_TYPES)
            ->where('type', '!=', 'info')
            ->whereNotNull('name')
            ->when(! empty($exceptNames), fn ($query) => $query->whereNotIn('name', $exceptNames))
            ->orderBy('sort')
            ->get()
            ->unique('name')
            ->mapWithKeys(fn (CustomFormField $field): array => [
                $field->name => $field->name . ' - ' . $this->transText($field->label ?: $field->name),
            ])
            ->toArray();
    }

    public function profileFieldByName(string $name): ?CustomFormField
    {
        $profileFormId = $this->profileFormId();

        if (! $profileFormId || trim($name) === '') {
            return null;
        }

        return CustomFormField::query()
            ->where('custom_form_id', $profileFormId)
            ->where('name', $name)
            ->first();
    }

    public function profileFieldsByName(array $names): Collection
    {
        $profileFormId = $this->profileFormId();

        $names = collect($names)
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! $profileFormId || empty($names)) {
            return collect();
        }

        return CustomFormField::query()
            ->where('custom_form_id', $profileFormId)
            ->whereIn('name', $names)
            ->orderBy('sort')
            ->get()
            ->unique('name')
            ->values();
    }

    private function profileFormId(): ?int
    {
        if (! Schema::hasTable('custom_forms')) {
            return null;
        }

        $id = CustomForm::query()
            ->where('slug', 'profile')
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function latestProfileData(int $profileFormId): array
    {
        $columns = Schema::getColumnListing('custom_form_entries');
        $ownerColumns = collect(['created_by', 'user_id', 'created_by_id'])
            ->filter(fn (string $column): bool => in_array($column, $columns, true))
            ->values()
            ->all();

        if (empty($ownerColumns)) {
            return [];
        }

        $query = CustomFormEntry::query()
            ->where('custom_form_id', $profileFormId)
            ->where(function ($query) use ($ownerColumns): void {
                foreach ($ownerColumns as $ownerColumn) {
                    $query->orWhere($ownerColumn, Auth::id());
                }
            });

        if (in_array('review_status', $columns, true)) {
            $query->where(function ($query): void {
                $query->whereNull('review_status')
                    ->orWhere('review_status', '!=', 'draft');
            });
        }

        if (in_array('status', $columns, true)) {
            $query->where(function ($query): void {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'draft');
            });
        }

        $entry = $query->latest('id')->first();

        if (! $entry) {
            return [];
        }

        $data = is_array($entry->data)
            ? $entry->data
            : json_decode((string) $entry->data, true);

        return is_array($data) ? $data : [];
    }

    private function targetFormIds(int $customFormId): array
    {
        $ids = [$customFormId];

        if (Schema::hasColumn('custom_forms', 'custom_form_id')) {
            $ids = array_merge(
                $ids,
                DB::table('custom_forms')
                    ->where('custom_form_id', $customFormId)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all()
            );
        }

        return collect($ids)->unique()->values()->all();
    }

    private function fieldsForForms(array $customFormIds): Collection
    {
        return DB::table('custom_form_fields')
            ->whereIn('custom_form_id', $customFormIds)
            ->whereNotNull('name')
            ->orderBy('custom_form_id')
            ->orderBy('sort')
            ->get();
    }

    private function resolveProfileValue(mixed $profileValue, ?object $profileField, object $targetField): mixed
    {
        if (is_array($profileValue) || is_object($profileValue)) {
            return $profileValue;
        }

        if (! $profileField) {
            return $profileValue;
        }

        $profileOptions = $this->normalizeOptions($profileField->options ?? []);
        $profileChoices = $this->normalizeChoices($profileOptions['choices'] ?? []);

        if (empty($profileChoices) || ! array_key_exists((string) $profileValue, $profileChoices)) {
            return $profileValue;
        }

        $targetType = $this->normalizeType($targetField->type ?? '');
        $targetOptions = $this->normalizeOptions($targetField->options ?? []);
        $targetChoices = $this->normalizeChoices($targetOptions['choices'] ?? []);

        if (
            in_array($targetType, self::SELECT_TYPES, true)
            && array_key_exists((string) $profileValue, $targetChoices)
        ) {
            return $profileValue;
        }

        return $this->transText($profileChoices[(string) $profileValue]);
    }

    private function normalizeOptions(mixed $options): array
    {
        if (is_array($options)) {
            return $options;
        }

        if (is_string($options) && $options !== '') {
            $decoded = json_decode($options, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_object($options)) {
            return json_decode(json_encode($options), true) ?: [];
        }

        return [];
    }

    private function normalizeChoices(mixed $choices): array
    {
        if (! is_array($choices)) {
            return [];
        }

        $normalized = [];

        foreach ($choices as $key => $label) {
            if (is_array($label) && array_key_exists('value', $label)) {
                $normalized[(string) $label['value']] = $label['label'] ?? $label['value'];

                continue;
            }

            $normalized[(string) $key] = $label;
        }

        return $normalized;
    }

    private function normalizeType(mixed $type): string
    {
        return Str::of((string) $type)
            ->lower()
            ->replace('-', '_')
            ->snake()
            ->toString();
    }

    private function transText(mixed $value): string
    {
        $locale = strtolower((string) app()->getLocale());

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);
        }

        if (is_array($value)) {
            return (string) (
                $value[$locale]
                ?? $value['km']
                ?? $value['kh']
                ?? $value['en']
                ?? collect($value)->first()
                ?? ''
            );
        }

        return (string) $value;
    }
}
