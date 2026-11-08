<?php

namespace App\Models;

use App\Enums\WorkflowPaymentSource;
use App\Enums\WorkflowStageType as StageBehavior;
use App\Support\WorkflowStageTypeSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkflowStageType extends Model
{
    protected $fillable = ['label_en', 'label_km', 'behavior', 'is_active', 'review_actions', 'stage_settings', 'enabled_actions'];

    protected $attributes = ['is_active' => true, 'is_system' => false];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_system' => 'boolean', 'review_actions' => 'array', 'stage_settings' => 'array', 'enabled_actions' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $type): void {
            $type->label_en = trim((string) $type->label_en);
            $type->label_km = trim((string) $type->label_km);

            if ($type->exists && $type->isDirty('behavior')) {
                throw ValidationException::withMessages(['behavior' => __('workflow_stage_types.validation.behavior_immutable')]);
            }

            if ($type->behavior !== StageBehavior::Review->value) {
                $type->review_actions = null;
            } else {
                $type->stage_settings = null;
            }

            $rules = [
                'label_en' => ['required', 'string', 'max:255', Rule::unique('workflow_stage_types', 'label_en')->ignore($type->getKey())],
                'label_km' => ['required', 'string', 'max:255', Rule::unique('workflow_stage_types', 'label_km')->ignore($type->getKey())],
                'behavior' => ['required', Rule::in(array_keys(StageBehavior::options()))],
                'enabled_actions' => ['nullable', 'array', 'min:1'],
                'enabled_actions.*' => ['required', 'string', 'distinct:strict', Rule::in(array_keys(WorkflowStageTypeSettings::actions((string) $type->behavior)))],
            ];

            $rules = array_merge(
                $rules,
                WorkflowStageTypeSettings::rules(StageBehavior::Review->value, ''),
                WorkflowStageTypeSettings::rules($type->behavior === StageBehavior::Review->value ? '' : (string) $type->behavior),
            );

            Validator::make([
                ...$type->getAttributes(),
                'review_actions' => $type->review_actions,
                'stage_settings' => $type->stage_settings,
                'enabled_actions' => $type->enabled_actions,
            ], $rules)->validate();

            if ($type->enabled_actions !== null) {
                $type->enabled_actions = $type->enabledActionKeys();
            }

            if ($type->behavior === StageBehavior::Review->value) {
                $normalized = WorkflowNotification::normalizeStages([[
                    'stage_type' => StageBehavior::Review->value,
                    'review_actions' => is_array($type->review_actions) ? $type->review_actions : [],
                ]]);
                $actions = $normalized[0]['review_actions'];
                $type->review_actions = self::hasReviewActionSettings($actions) ? $actions : [];
            } elseif (is_array($type->stage_settings)) {
                $normalized = WorkflowNotification::normalizeStages([[
                    ...$type->stage_settings,
                    'stage_type' => $type->behavior,
                ]], applyTypeDefaults: false);
                $settings = WorkflowStageTypeSettings::onlyAllowed($type->behavior, $normalized[0]);
                $type->stage_settings = self::hasReviewActionSettings($settings) ? $settings : [];
            }
        });

        static::deleting(function (self $type): void {
            if (! $type->canDelete()) {
                throw ValidationException::withMessages(['stage_type' => __('workflow_stage_types.validation.delete_protected')]);
            }
        });
    }

    public function label(): string
    {
        return in_array(app()->getLocale(), ['km', 'kh'], true)
            ? (string) ($this->label_km ?: $this->label_en)
            : (string) ($this->label_en ?: $this->label_km);
    }

    public function labels(): array
    {
        return ['en' => (string) $this->label_en, 'km' => (string) $this->label_km];
    }

    public function actionDefaults(): array
    {
        $actions = $this->behavior === StageBehavior::Review->value && is_array($this->review_actions)
            ? array_intersect_key($this->review_actions, array_flip($this->enabledActionKeys()))
            : [];

        return self::hasReviewActionSettings($actions) ? $actions : [];
    }

    public function enabledActionKeys(): array
    {
        return array_keys(WorkflowStageTypeSettings::selectedActions((string) $this->behavior, $this->enabled_actions));
    }

    public function enabledButtonKeys(): array
    {
        return array_values(array_intersect($this->enabledActionKeys(), array_keys(WorkflowStageTypeSettings::buttonActions())));
    }

    /** Defaults use the same state paths as the existing workflow form and engine. */
    public function stageDefaults(mixed $paymentSource = null): array
    {
        if ($this->behavior === StageBehavior::Review->value) {
            $actions = $this->actionDefaults();
            $settings = $actions === [] ? [] : ['review_actions' => $actions];
        } else {
            $settings = [];
            foreach ($this->enabledActionKeys() as $action) {
                foreach (WorkflowStageTypeSettings::fields((string) $this->behavior, $action) as $field) {
                    if (Arr::has((array) $this->stage_settings, $field['path'])) {
                        Arr::set($settings, $field['path'], Arr::get($this->stage_settings, $field['path']));
                    }
                }
            }
        }

        // Catalog recipients are local roles; AMS recipient choices remain per workflow.
        if ($this->behavior === StageBehavior::Payment->value
            && WorkflowPaymentSource::resolve($paymentSource) === WorkflowPaymentSource::Ams) {
            unset($settings['audience_messages']['staff_recipient_roles']);
            if (($settings['audience_messages'] ?? null) === []) {
                unset($settings['audience_messages']);
            }
        }

        $settings = self::hasReviewActionSettings($settings) ? $settings : [];
        if ($this->enabled_actions !== null) {
            $settings['enabled_actions'] = $this->enabledActionKeys();
        }

        return $settings;
    }

    public static function hasReviewActionSettings(mixed $settings): bool
    {
        if (! is_array($settings)) {
            return false;
        }

        foreach ($settings as $value) {
            if (is_array($value) ? self::hasReviewActionSettings($value) : filled($value)) {
                return true;
            }
        }

        return false;
    }

    public function inUse(): bool
    {
        if (! $this->exists || ! Schema::hasTable('workflow_notifications')) {
            return false;
        }

        return WorkflowNotification::query()->get(['stages'])->contains(function (WorkflowNotification $workflow): bool {
            foreach ($workflow->stages ?? [] as $stage) {
                $data = is_array($stage['data'] ?? null) ? $stage['data'] : $stage;
                if (is_array($data) && (string) ($data['stage_type_id'] ?? '') === (string) $this->getKey()) {
                    return true;
                }
            }

            return false;
        });
    }

    public function canDelete(): bool
    {
        return ! $this->is_system && ! $this->inUse();
    }

    public static function selected(mixed $selection): ?self
    {
        if ((! is_int($selection) && ! is_string($selection))
            || ! ctype_digit((string) $selection) || (int) $selection <= 0
            || ! Schema::hasTable('workflow_stage_types')) {
            return null;
        }

        return static::query()->find((int) $selection);
    }

    public static function selectionForBehavior(?string $behavior): int|string|null
    {
        if (blank($behavior)) {
            return null;
        }

        if ($behavior === 'verification') {
            self::ensureVerificationAvailable();
        }

        if (Schema::hasTable('workflow_stage_types')) {
            $type = static::query()->where('behavior', $behavior)->where('is_system', true)->first();
            if ($type) {
                return (int) $type->getKey();
            }
        }

        return 'legacy:'.$behavior;
    }

    public static function selectOptions(mixed $selected = null, ?string $legacyBehavior = null): array
    {
        $options = [];
        self::ensureVerificationAvailable();

        if (Schema::hasTable('workflow_stage_types')) {
            $types = static::query()->where('is_active', true)->orderForDisplay()->get();
            foreach ($types as $type) {
                $options[$type->getKey()] = $type->label();
            }

            if ($current = static::selected($selected)) {
                $options[$current->getKey()] ??= $current->label().' ('.__('workflow_stage_types.states.inactive').')';
            }
        } else {
            foreach (StageBehavior::options() as $behavior => $label) {
                $options['legacy:'.$behavior] = $label;
            }
        }

        if (filled($legacyBehavior) && $selected === 'legacy:'.$legacyBehavior) {
            $options[$selected] = self::defaultLabels($legacyBehavior)[app()->getLocale() === 'km' ? 'km' : 'en'];
        }

        return $options;
    }

    /** Present the built-in stages in order, followed by custom stages in creation order. */
    public function scopeOrderForDisplay(Builder $query): Builder
    {
        $behaviors = array_keys(StageBehavior::options());
        $grammar = $query->getQuery()->getGrammar();
        $systemColumn = $grammar->wrap($query->qualifyColumn('is_system'));
        $behaviorColumn = $grammar->wrap($query->qualifyColumn('behavior'));
        $cases = [];
        foreach (array_keys($behaviors) as $rank) {
            $cases[] = 'WHEN ? THEN '.$rank;
        }
        $fallback = count($behaviors);

        return $query->orderByRaw(
            'CASE WHEN '.$systemColumn.' THEN CASE '.$behaviorColumn.' '.implode(' ', $cases).' ELSE '.$fallback.' END ELSE '.$fallback.' END',
            $behaviors,
        )->orderBy($query->qualifyColumn('id'));
    }

    /** Make the new behavior available to installations whose catalog was already seeded. */
    private static function ensureVerificationAvailable(): void
    {
        if (! Schema::hasTable('workflow_stage_types')) {
            return;
        }

        $stockLabels = [
            'label_en' => 'Registrar Verification',
            'label_km' => 'ការផ្ទៀងផ្ទាត់ដោយការិយាល័យចុះឈ្មោះ',
        ];
        $existing = static::query()->where('behavior', 'verification')->where('is_system', true)->first();
        if ($existing && $existing->label_en !== $stockLabels['label_en'] && $existing->label_km !== $stockLabels['label_km']) {
            return;
        }

        DB::transaction(static function () use ($stockLabels): void {
            // Existing catalogs contain seeded rows; serialize concurrent first-time availability checks.
            static::query()->orderBy('id')->lockForUpdate()->first();
            $existing = static::query()->where('behavior', 'verification')->where('is_system', true)->lockForUpdate()->first();

            $labels = self::defaultLabels('verification');
            $values = [];
            foreach (['label_en' => 'en', 'label_km' => 'km'] as $column => $locale) {
                if ($existing && $existing->{$column} !== $stockLabels[$column]) {
                    continue;
                }

                $base = $labels[$locale];
                $suffix = 2;
                while (static::query()->where($column, $labels[$locale])
                    ->when($existing, fn ($query) => $query->whereKeyNot($existing->getKey()))->exists()) {
                    $labels[$locale] = $base.' ('.$suffix++.')';
                }
                $values[$column] = $labels[$locale];
            }

            if ($existing) {
                if ($values !== []) {
                    DB::table('workflow_stage_types')->where('id', $existing->getKey())
                        ->update([...$values, 'updated_at' => now()]);
                }

                return;
            }

            DB::table('workflow_stage_types')->insertOrIgnore([
                ...$values,
                'behavior' => 'verification',
                'is_system' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /** Keep the engine key authoritative even if a client submits a different hidden value. */
    public static function bindStage(array $stage, bool $applyDefaults = true): array
    {
        if (blank($stage['stage_type_id'] ?? null)) {
            unset($stage['stage_type_id']);

            return $stage;
        }

        $type = static::selected($stage['stage_type_id']);
        if (! $type || ! in_array($type->behavior, array_keys(StageBehavior::options()), true)) {
            throw ValidationException::withMessages(['stages' => __('workflow_stage_types.validation.invalid_selection')]);
        }

        $stage['stage_type_id'] = (int) $type->getKey();
        $stage['stage_type'] = $type->behavior;
        $stage['stage_type_labels'] = $type->labels();

        // An explicit workflow configuration, including an empty one, remains authoritative.
        if ($applyDefaults) {
            foreach ($type->stageDefaults($stage['payment_source'] ?? null) as $key => $value) {
                if (! array_key_exists($key, $stage)) {
                    $stage[$key] = $value;
                }
            }
        }

        return $stage;
    }

    public static function labelsForStage(array $stage): array
    {
        if ($type = static::selected($stage['stage_type_id'] ?? null)) {
            return $type->labels();
        }

        if (filled($stage['stage_type'] ?? null) && Schema::hasTable('workflow_stage_types')) {
            $type = static::query()->where('behavior', $stage['stage_type'])->where('is_system', true)->first();
            if ($type) {
                return $type->labels();
            }
        }

        return is_array($stage['stage_type_labels'] ?? null)
            ? $stage['stage_type_labels']
            : self::defaultLabels((string) ($stage['stage_type'] ?? ''));
    }

    public static function labelForStage(array $stage, bool $preferSaved = false): string
    {
        // Older snapshots without catalog metadata retain their original enum labels.
        $labels = $preferSaved
            ? (is_array($stage['stage_type_labels'] ?? null) ? $stage['stage_type_labels'] : self::defaultLabels((string) ($stage['stage_type'] ?? '')))
            : self::labelsForStage($stage);
        $locale = in_array(app()->getLocale(), ['km', 'kh'], true) ? 'km' : 'en';

        return (string) ($labels[$locale] ?? $labels['en'] ?? $labels['km'] ?? $stage['stage_type'] ?? '');
    }

    public static function defaultLabels(string $behavior): array
    {
        if (! StageBehavior::tryFrom($behavior)) {
            return ['en' => $behavior, 'km' => $behavior];
        }

        return [
            'en' => Lang::get('workflow_notifications.stage_types.'.$behavior, [], 'en'),
            'km' => Lang::get('workflow_notifications.stage_types.'.$behavior, [], 'km'),
        ];
    }
}
