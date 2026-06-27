<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class GeoLocation extends Model
{
    use HasFactory;

    protected $table = 'geo_locations';

    protected $fillable = [
        'name_kh',
        'name_en',
        'code',
        'type',
        'parent_id',
        'metadata',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (GeoLocation $geoLocation): void {
            $geoLocation->code = self::normalizeCode($geoLocation->code, $geoLocation->type);

            if ($geoLocation->type === 'province') {
                $geoLocation->parent_id = null;
            }

            self::validateCodeLength($geoLocation);
            self::validateParent($geoLocation);
            self::validateCodePrefix($geoLocation);
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public static function expectedCodeDigits(?string $type): ?int
    {
        return match ($type) {
            'province' => 2,
            'district' => 4,
            'commune' => 6,
            'village' => 8,
            default => null,
        };
    }

    public static function parentTypeFor(?string $type): ?string
    {
        return match ($type) {
            'district' => 'province',
            'commune' => 'district',
            'village' => 'commune',
            default => null,
        };
    }

    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            'province' => 'Province / Capital',
            'district' => 'District / Khan',
            'commune' => 'Commune / Sangkat',
            'village' => 'Village',
            default => 'Geo Location',
        };
    }

    public static function normalizeCode(mixed $code, ?string $type): string
    {
        $code = trim((string) $code);

        if (str_contains($code, '-')) {
            $parts = explode('-', $code);
            $code = end($parts);
        }

        $code = preg_replace('/[^0-9]/', '', $code);

        $digits = self::expectedCodeDigits($type);

        if (! $digits) {
            return $code;
        }

        if (strlen($code) > $digits) {
            $code = substr($code, -$digits);
        }

        return str_pad($code, $digits, '0', STR_PAD_LEFT);
    }

    private static function validateCodeLength(GeoLocation $geoLocation): void
    {
        $expectedDigits = self::expectedCodeDigits($geoLocation->type);

        if (! $expectedDigits) {
            throw ValidationException::withMessages([
                'data.type' => 'Invalid geo location type.',
            ]);
        }

        if (strlen((string) $geoLocation->code) !== $expectedDigits) {
            throw ValidationException::withMessages([
                'data.code' => self::typeLabel($geoLocation->type) . ' code must be exactly ' . $expectedDigits . ' digits.',
            ]);
        }
    }

    private static function validateParent(GeoLocation $geoLocation): void
    {
        $expectedParentType = self::parentTypeFor($geoLocation->type);

        if (! $expectedParentType) {
            return;
        }

        if (blank($geoLocation->parent_id)) {
            throw ValidationException::withMessages([
                'data.parent_id' => self::typeLabel($geoLocation->type) . ' must have a parent location.',
            ]);
        }

        $parent = self::query()->find($geoLocation->parent_id);

        if (! $parent) {
            throw ValidationException::withMessages([
                'data.parent_id' => 'Parent location was not found.',
            ]);
        }

        if ($parent->type !== $expectedParentType) {
            throw ValidationException::withMessages([
                'data.parent_id' => self::typeLabel($geoLocation->type) . ' parent must be ' . self::typeLabel($expectedParentType) . '.',
            ]);
        }
    }

    private static function validateCodePrefix(GeoLocation $geoLocation): void
    {
        if ($geoLocation->type === 'province') {
            return;
        }

        if (blank($geoLocation->parent_id)) {
            return;
        }

        $parent = self::query()->find($geoLocation->parent_id);

        if (! $parent) {
            return;
        }

        $parentCode = self::normalizeCode($parent->code, $parent->type);
        $currentCode = self::normalizeCode($geoLocation->code, $geoLocation->type);

        if (! str_starts_with($currentCode, $parentCode)) {
            throw ValidationException::withMessages([
                'data.code' => self::typeLabel($geoLocation->type) . ' code must start with parent code ' . $parentCode . '.',
            ]);
        }
    }
}