<?php

namespace Chanthoeun\FilamentCustomForms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Chanthoeun\FilamentCustomForms\CustomFormPlugin;

class CustomFormEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'custom_form_id',
        'data',
        'created_by',
    ];

    protected $casts = [
        'data' => 'array',
        'workflow_snapshot' => 'array',
        'form_entries_deleted_at' => 'datetime',
        'payment_status_lists_deleted_at' => 'datetime',
        'entrance_statistics_deleted_at' => 'datetime',
        'passed_entrance_deleted_at' => 'datetime',
        'passed_exit_deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $entry): void {
            \App\Support\CandidateApplicationContext::stamp($entry);
            \App\Support\WorkflowEntrySnapshot::capture($entry);

            if ($entry->isDirty('data')) {
                $data = is_array($entry->data) ? $entry->data : [];
                $protectedKeys = ['application_received_at', 'application_received_by'];
                if (\App\Support\WorkflowStageProgression::enabled($entry)) {
                    $protectedKeys[] = 'workflow_progress';
                }
                $stored = [];
                if ($entry->exists) {
                    $stored = \Illuminate\Support\Facades\DB::table($entry->getTable())
                        ->where($entry->getKeyName(), $entry->getKey())->value('data');
                    $stored = is_string($stored) ? json_decode($stored, true, flags: JSON_THROW_ON_ERROR) : $stored;
                }
                foreach ($protectedKeys as $key) {
                    unset($data[$key]);
                    if (is_array($stored) && array_key_exists($key, $stored)
                        && ($key !== 'workflow_progress' || is_array($stored[$key]))) {
                        $data[$key] = $stored[$key];
                    }
                }
                $entry->data = $data;
            }
        });

        static::created(function (self $entry): void {
            \App\Support\WorkflowStageProgression::initialize($entry);
        });
    }

    public function customForm(): BelongsTo
    {
        return $this->belongsTo(CustomFormPlugin::get()->getFormModel());
    }

    public function creator(): BelongsTo
    {
        $userModel = CustomFormPlugin::get()->getUserModel();

        if (!class_exists($userModel)) {
            return $this->belongsTo(\Illuminate\Database\Eloquent\Model::class, 'created_by')->withDefault([
                'name' => 'Guest',
            ]);
        }

        return $this->belongsTo($userModel, 'created_by')->withDefault([
            'name' => 'Guest',
        ]);
    }
}
