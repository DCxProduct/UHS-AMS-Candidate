<?php

namespace App\Models;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class ClosingDate extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'closing_dates';

    protected $fillable = [
        'name',
        'type',
        'status',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public const TYPE_PREFIX_CUSTOM_FORM = 'custom_form:';

    public const STATUS_NOT_OPEN = 'not_open';
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    public static function customFormTypeKey(int|string $customFormId): string
    {
        return self::TYPE_PREFIX_CUSTOM_FORM . $customFormId;
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_NOT_OPEN => 'Not Open',
            self::STATUS_OPEN => 'Open',
            self::STATUS_CLOSED => 'Closed',
        ];
    }

    public static function typeOptions(): array
    {
        if (! Schema::hasTable('custom_forms')) {
            return [];
        }

        $query = CustomForm::query();

        if (Schema::hasColumn('custom_forms', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('custom_forms', 'display_order')) {
            $query->orderBy('display_order');
        }

        $forms = $query
            ->orderBy('id')
            ->get(['id', 'custom_form_id', 'name', 'menu_placement', 'sub_item_type']);

        $parentNames = $forms
            ->keyBy('id')
            ->map(fn ($form): string => self::displayFormName($form->name))
            ->toArray();

        return $forms
            ->mapWithKeys(fn ($form): array => [
                self::customFormTypeKey($form->id) => self::displayTypeOptionLabel($form, $parentNames),
            ])
            ->toArray();
    }

    protected static function displayTypeOptionLabel(object $form, array $parentNames): string
    {
        $formName = self::displayFormName($form->name);

        if (($form->menu_placement ?? null) === 'sub_item') {
            $parentName = $parentNames[(int) ($form->custom_form_id ?? 0)] ?? __('closing_dates.parent_form');

            return __('closing_dates.sub_form') . ": {$parentName} → {$formName}";
        }

        return __('closing_dates.parent_form') . ": {$formName}";
    }

    protected static function displayFormName(mixed $value): string
    {
        $locale = app()->getLocale();

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return (string) (
                    $decoded[$locale]
                    ?? $decoded['km']
                    ?? $decoded['kh']
                    ?? $decoded['en']
                    ?? collect($decoded)->first()
                    ?? ''
                );
            }
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

    public static function getDeadlineByCustomFormId(int|string|null $customFormId): ?self
    {
        if (blank($customFormId)) {
            return null;
        }

        return self::query()
            ->where('type', self::customFormTypeKey($customFormId))
            ->latest('id')
            ->first();
    }

    public static function isCustomFormOpen(int|string|null $customFormId): bool
    {
        $deadline = self::getDeadlineByCustomFormId($customFormId);

        if (! $deadline) {
            return false;
        }

        return $deadline->status === self::STATUS_OPEN
            && now()->toDateString() >= $deadline->start_date?->toDateString()
            && now()->toDateString() <= $deadline->end_date?->toDateString();
    }

    public static function shouldShowCustomForm(int|string|null $customFormId): bool
    {
        return self::isCustomFormOpen($customFormId);
    }

    public static function shouldShowContact(int|string|null $customFormId): bool
    {
        $deadline = self::getDeadlineByCustomFormId($customFormId);

        return $deadline?->status === self::STATUS_CLOSED;
    }

    public static function isCustomFormClosed(int|string|null $customFormId): bool
    {
        $deadline = self::getDeadlineByCustomFormId($customFormId);

        if (! $deadline) {
            return false;
        }

        return $deadline->status === self::STATUS_CLOSED;
    }

    public static function getOpenDynamicForms()
    {
        if (! Schema::hasTable('custom_forms') || ! Schema::hasTable('closing_dates')) {
            return collect();
        }

        return CustomForm::query()
            ->where('is_active', true)
            ->whereNotNull('name')
            ->whereIn('id', function ($query) {
                $query->selectRaw("CAST(REPLACE(type, 'custom_form:', '') AS UNSIGNED)")
                    ->from('closing_dates')
                    ->whereNull('deleted_at')
                    ->where('status', self::STATUS_OPEN);
            })
            ->orderBy('id')
            ->get();
    }

    public static function isApplicationOpen(string $slug): bool
    {
        return self::query()
            ->where('type', $slug)
            ->where('status', 'open')
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->exists();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusOptions()[$this->status] ?? 'Not Open';
    }
}
