<?php

namespace App\Models;

use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class AmsPaymentRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'attempts' => 'integer', 'last_event_version' => 'integer', 'synced_at' => 'datetime'];
    }

    public static function hasPaidForEntry(int|string|null $entryId): bool
    {
        return filled($entryId)
            && Schema::hasColumn('ams_payment_requests', 'payment_status')
            && static::query()->where('custom_form_entry_id', $entryId)->where('payment_status', 'paid')->exists();
    }

    public static function paidForApplicationQuery(): Builder
    {
        if (! Schema::hasColumn('ams_payment_requests', 'payment_status')) {
            return static::query()->fromRaw('(SELECT 1) as unavailable_ams_payment_status')
                ->selectRaw('1')->whereRaw('1 = 0');
        }

        return static::query()->selectRaw('1')
            ->whereColumn('ams_payment_requests.custom_form_entry_id', 'custom_form_entries.id')
            ->where('payment_status', 'paid');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(CustomFormEntry::class, 'custom_form_entry_id');
    }
}
