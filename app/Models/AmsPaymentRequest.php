<?php

namespace App\Models;

use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmsPaymentRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'attempts' => 'integer', 'last_event_version' => 'integer', 'synced_at' => 'datetime'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(CustomFormEntry::class, 'custom_form_entry_id');
    }
}
