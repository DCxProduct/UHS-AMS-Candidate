<?php

namespace App\Filament\Admin\Resources\WorkflowStageTypes\Schemas;

use App\Filament\Admin\Resources\WorkflowButtons\WorkflowButtonResource;
use App\Models\WorkflowButton;
use App\Models\WorkflowStageType;
use App\Support\WorkflowStageTypeSettings;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

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

            Select::make('workflow_button_ids')
                ->label(__('workflow_stage_types.fields.workflow_buttons'))
                ->helperText(__('workflow_stage_types.form.workflow_buttons_help'))
                ->required(fn (?Model $record): bool => ! $record?->exists || $record->workflow_button_ids !== null)
                ->validationMessages(['required' => __('workflow_stage_types.validation.workflow_buttons_required')])
                ->multiple()
                ->searchable()
                ->native(false)
                ->options(fn (Get $get): array => WorkflowButton::selectOptions($get('workflow_button_ids')))
                ->default([])
                ->dehydrateStateUsing(fn (mixed $state): ?array => filled($state) ? array_values($state) : null)
                ->live()
                ->afterStateUpdated(function (mixed $state, Set $set, string $operation, ?Model $record): void {
                    $buttons = WorkflowButton::query()->whereKey(is_array($state) ? $state : [])->get();
                    $actions = $buttons->pluck('action_key')->all();
                    if ($actions === [] && $record?->exists && $record->workflow_button_ids === null) {
                        $set('enabled_actions', $record->enabled_actions);

                        return;
                    }
                    $set('enabled_actions', $actions);
                    if ($operation !== 'edit' && ($behavior = WorkflowStageTypeSettings::behaviorForSelection($actions)) !== null) {
                        $set('behavior', $behavior);
                    }
                })
                ->rules([
                    fn (?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        if (blank($value)) {
                            return;
                        }
                        try {
                            $definitions = (new WorkflowStageType(['workflow_button_ids' => $value]))->buttonDefinitions();
                            $behavior = WorkflowStageTypeSettings::behaviorForSelection(array_column($definitions, 'action_key'));
                            if ($record?->exists && $behavior !== $record->behavior) {
                                $fail(__('workflow_stage_types.validation.enabled_actions_group_immutable'));
                            }
                        } catch (ValidationException $exception) {
                            $fail(collect($exception->errors())->flatten()->first());
                        }
                    },
                ])
                ->suffixAction(Action::make('manageWorkflowButtons')
                    ->label(__('workflow_buttons.navigation_label'))
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn (): string => WorkflowButtonResource::getUrl('index'))
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => WorkflowButtonResource::canViewAny()))
                ->columnSpanFull(),

            Hidden::make('behavior')
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->dehydrated(false),

            Hidden::make('enabled_actions')
                ->dehydrated(false),

            Toggle::make('is_active')
                ->label(__('workflow_stage_types.fields.is_active'))
                ->default(true),
        ];
    }
}
