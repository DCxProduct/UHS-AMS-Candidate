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
    ];

    protected static function booted(): void
    {
        static::saving(function (self $entry): void {
            \App\Support\WorkflowEntrySnapshot::capture($entry);

            if ($entry->isDirty('data') && \App\Support\WorkflowStageProgression::enabled($entry)) {
                $data = is_array($entry->data) ? $entry->data : [];
                unset($data['workflow_progress']);
                if ($entry->exists) {
                    $stored = \Illuminate\Support\Facades\DB::table($entry->getTable())
                        ->where($entry->getKeyName(), $entry->getKey())->value('data');
                    $stored = is_string($stored) ? json_decode($stored, true, flags: JSON_THROW_ON_ERROR) : $stored;
                    if (is_array($stored['workflow_progress'] ?? null)) {
                        $data['workflow_progress'] = $stored['workflow_progress'];
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
