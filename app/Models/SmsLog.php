<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One SMS handed to PlasGate. "sent" means PlasGate accepted it; delivery to
 * the phone is reported in the PlasGate account.
 */
class SmsLog extends Model
{
    public const SOURCES = ['workflow', 'password_reset', 'notification', 'test'];

    protected $fillable = [
        'user_id',
        'source',
        'phone',
        'sent_to',
        'content',
        'status',
        'response',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
