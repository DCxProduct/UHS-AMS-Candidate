<?php

namespace Chanthoeun\FilamentCustomForms\Models;

use App\Support\PassedResultMenuOptions;
use App\Support\CandidateStatisticsSynchronizer;
use App\Support\StatisticsMenuOptions;
use App\Support\UserTypeOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class CustomForm extends Model
{
    use HasFactory, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Profile form
    |--------------------------------------------------------------------------
    | The Profile form is always available for every candidate type and is not
    | driven by a closing date. Every other form keeps the old workflow.
    |--------------------------------------------------------------------------
    */
    public const PROFILE_SLUG = 'profile';

    protected static array $profileFormIdCache = [];

    protected $fillable = [
        'name',
        'slug',
        'schema',
        'is_active',
        'allowed_roles',
        'requires_payment',
        'passed_result_menu',
        'statistics_menu',
        'display_order',
    ];

    protected $casts = [
        'schema' => 'array',
        'is_active' => 'boolean',
        'allowed_roles' => 'array',
        'requires_payment' => 'boolean',
        'display_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (CustomForm $customForm): void {
            if (blank($customForm->display_order)) {
                $customForm->display_order = ((int) static::query()->max('display_order')) + 1;
            }
        });

        static::saving(function (CustomForm $customForm): void {
            if (static::isProfileSlug($customForm->slug)) {
                $customForm->allowed_roles = static::profileAllowedRoles();
            }

            $customForm->passed_result_menu = PassedResultMenuOptions::normalize($customForm->passed_result_menu);
            $customForm->statistics_menu = StatisticsMenuOptions::normalize($customForm->statistics_menu);
        });

        static::saved(function (CustomForm $customForm): void {
            CandidateStatisticsSynchronizer::syncForm($customForm);
        });
    }

    public static function isProfileSlug(mixed $slug): bool
    {
        return strtolower(trim((string) $slug)) === self::PROFILE_SLUG;
    }

    public function isProfileForm(): bool
    {
        return static::isProfileSlug($this->slug);
    }

    public static function isProfileFormId(int|string|null $customFormId): bool
    {
        if (blank($customFormId)) {
            return false;
        }

        $key = (string) $customFormId;

        if (array_key_exists($key, static::$profileFormIdCache)) {
            return static::$profileFormIdCache[$key];
        }

        if (! Schema::hasTable('custom_forms')) {
            return static::$profileFormIdCache[$key] = false;
        }

        $slug = static::withTrashed()
            ->whereKey($customFormId)
            ->value('slug');

        return static::$profileFormIdCache[$key] = static::isProfileSlug($slug);
    }

    /*
    |--------------------------------------------------------------------------
    | Every candidate type is allowed on the Profile form
    |--------------------------------------------------------------------------
    */
    public static function profileAllowedRoles(): array
    {
        return collect(array_keys(UserTypeOptions::options()))
            ->map(fn ($role): string => strtolower(trim((string) $role)))
            ->filter(fn (string $role): bool => filled($role))
            ->unique()
            ->values()
            ->all();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CustomFormEntry::class);
    }

    public function authorizations(): HasMany
    {
        return $this->hasMany(CustomFormAuthorization::class, 'custom_form_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CustomFormField::class, 'custom_form_id')->orderBy('sort');
    }

    public function getDisplayNameAttribute(): string
    {
        return self::localeText($this->name);
    }

    public static function localeText(mixed $value): string
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
                ?? ''
            );
        }

        return (string) $value;
    }
}
