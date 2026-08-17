<?php

namespace App\Models;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'users_id',
        'form_id',
        'receipt_number',
        'payment_slip_path',
        'type_payment',
        'exchange_rate',
        'status_payt',
        'amount_usd',
        'amount_kh',
        'datetime_pay',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:2',
            'amount_usd' => 'decimal:2',
            'amount_kh' => 'decimal:2',
            'datetime_pay' => 'datetime',
            'status' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(CustomForm::class, 'form_id');
    }

    public function paymentSlipUrl(): ?string
    {
        if (blank($this->payment_slip_path)) {
            return null;
        }

        return asset('storage/' . ltrim((string) $this->payment_slip_path, '/'));
    }
}
