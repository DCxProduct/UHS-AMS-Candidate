<?php

namespace App\Filament\Admin\Resources\WorkflowStageTypes\Schemas;

use App\Enums\WorkflowStageType as StageBehavior;
use App\Models\WorkflowStageType;
use App\Support\WorkflowStageTypeSettings;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class WorkflowStageTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('workflow_stage_types.form.section_title'))
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema(self::fields()),
            ]);
    }

    private static function fields(): array
    {
        return [
            TextInput::make('label_km')
                ->label(__('workflow_stage_types.fields.label_km'))
                ->placeholder(__('workflow_stage_types.fields.label_km'))
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            TextInput::make('label_en')
                ->label(__('workflow_stage_types.fields.label_en'))
                ->placeholder(__('workflow_stage_types.fields.label_en'))
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            Select::make('behavior')
                ->label(__('workflow_stage_types.fields.behavior'))
                ->options(StageBehavior::options())
                ->hidden()
                ->dehydratedWhenHidden()
                ->live()
                ->afterStateUpdated(function (?string $state, ?string $old, Set $set, string $operation): void {
                    if ($operation === 'edit' || $state === $old) {
                        return;
                    }

                    $set('enabled_actions', array_keys(array_filter(WorkflowStageTypeSettings::buttonActions(), fn (array $action): bool => $action['behavior'] === $state)));
                })
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->dehydrated(fn (string $operation): bool => $operation !== 'edit'),

            CheckboxList::make('enabled_actions')
                ->view('filament.admin.workflow-stage-types.action-picker')
                ->label(__('workflow_stage_types.fields.enabled_actions'))
                ->helperText(fn (?Model $record): string => $record instanceof WorkflowStageType && $record->exists
                    ? __('workflow_stage_types.form.enabled_actions_edit_help', [
                        'group' => __('workflow_stage_types.groups.'.$record->behavior),
                    ])
                    : __('workflow_stage_types.form.enabled_actions_create_help'))
                ->options(fn (): array => array_map(fn (array $action): string => $action['label'], WorkflowStageTypeSettings::buttonActions()))
                ->disableOptionWhen(fn (string $value, ?Model $record, string $operation): bool => $operation === 'edit'
                    && $record instanceof WorkflowStageType
                    && (WorkflowStageTypeSettings::buttonActions()[$value]['behavior'] ?? null) !== $record->behavior)
                ->default([])
                ->afterStateHydrated(function (CheckboxList $component, ?Model $record): void {
                    if ($record instanceof WorkflowStageType) {
                        $component->state($record->enabledButtonKeys());
                    }
                })
                ->afterStateUpdated(function (mixed $state, Get $get, Set $set, string $operation): void {
                    if ($operation === 'edit') {
                        return;
                    }

                    $behavior = WorkflowStageTypeSettings::behaviorForSelection($state);

                    if ($behavior === null || $behavior === $get('behavior')) {
                        return;
                    }

                    $set('behavior', $behavior);
                })
                ->required(fn (?Model $record): bool => ! self::hasLegacyStatusOnlySelection($record))
                ->minItems(fn (?Model $record): int => self::hasLegacyStatusOnlySelection($record) ? 0 : 1)
                ->in(fn (): array => array_keys(WorkflowStageTypeSettings::buttonActions()))
                ->rules([
                    fn (?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        if (! is_array($value) || $value === []) {
                            if (! is_array($value) || ! self::hasLegacyStatusOnlySelection($record)) {
                                $fail(__('workflow_stage_types.validation.enabled_actions_required'));
                            }

                            return;
                        }

                        $actions = WorkflowStageTypeSettings::buttonActions();
                        foreach ($value as $key) {
                            if (! is_string($key) || ! array_key_exists($key, $actions)) {
                                $fail(__('workflow_stage_types.validation.enabled_actions_invalid'));

                                return;
                            }
                        }

                        $behavior = WorkflowStageTypeSettings::behaviorForSelection($value);
                        if ($behavior === null) {
                            $fail(__('workflow_stage_types.validation.enabled_actions_same_group'));
                        } elseif ($record instanceof WorkflowStageType && $record->exists && $behavior !== $record->behavior) {
                            $fail(__('workflow_stage_types.validation.enabled_actions_group_immutable'));
                        }
                    },
                ])
                ->validationMessages([
                    'required' => __('workflow_stage_types.validation.enabled_actions_required'),
                    'min' => __('workflow_stage_types.validation.enabled_actions_required'),
                    'in' => __('workflow_stage_types.validation.enabled_actions_invalid'),
                ])
                ->columns(['default' => 1, 'md' => 3])
                ->gridDirection('row')
                ->live()
                ->columnSpanFull(),

            Toggle::make('is_active')
                ->label(__('workflow_stage_types.fields.is_active'))
                ->default(true),
        ];
    }

    private static function hasLegacyStatusOnlySelection(?Model $record): bool
    {
        return $record instanceof WorkflowStageType
            && $record->exists
            && $record->enabledButtonKeys() === []
            && $record->enabledActionKeys() !== [];
    }
}
