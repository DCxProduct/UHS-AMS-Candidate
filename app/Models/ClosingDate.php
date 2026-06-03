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

        return $query
            ->orderBy('id')
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($form): array => [
                self::customFormTypeKey($form->id) => $form->name,
            ])
            ->toArray();
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

        return $deadline->status === self::STATUS_OPEN;
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

    public function getStatusLabelAttribute(): string
    {
        return self::statusOptions()[$this->status] ?? 'Not Open';
    }
}
