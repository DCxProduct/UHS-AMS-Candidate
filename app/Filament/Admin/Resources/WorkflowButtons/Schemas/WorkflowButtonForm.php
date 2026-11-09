<?php

namespace App\Filament\Admin\Resources\WorkflowButtons\Schemas;

use App\Models\WorkflowButton;
use App\Support\WorkflowStageTypeSettings;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class WorkflowButtonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('workflow_buttons.form.section_title'))
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('label_en')
                            ->label(__('workflow_buttons.fields.label_en'))
                            ->placeholder(__('workflow_buttons.placeholders.label_en'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('label_km')
                            ->label(__('workflow_buttons.fields.label_km'))
                            ->placeholder(__('workflow_buttons.placeholders.label_km'))
                            ->helperText(__('workflow_buttons.helpers.label_km'))
                            ->maxLength(255),

                        Select::make('action_key')
                            ->label(__('workflow_buttons.fields.action_key'))
                            ->placeholder(__('workflow_buttons.placeholders.action_key'))
                            ->helperText(fn (?Model $record): string => $record instanceof WorkflowButton && $record->inUse()
                                ? __('workflow_buttons.helpers.action_locked')
                                : __('workflow_buttons.helpers.action_key'))
                            ->options(fn (): array => WorkflowButton::actionOptions())
                            ->in(fn (): array => array_keys(WorkflowButton::actionOptions()))
                            ->required()
                            ->disabled(fn (?Model $record): bool => $record instanceof WorkflowButton && $record->inUse())
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                $action = WorkflowStageTypeSettings::buttonActions()[$state] ?? null;
                                if ($action !== null) {
                                    $set('icon', $action['icon']);
                                }
                            })
                            ->columnSpanFull(),

                        Select::make('icon')
                            ->label(__('workflow_buttons.fields.icon'))
                            ->placeholder(__('workflow_buttons.placeholders.icon'))
                            ->helperText(__('workflow_buttons.helpers.icon'))
                            ->options(fn (): array => collect(WorkflowButton::iconOptions())
                                ->mapWithKeys(fn (string $label, string $icon): array => [
                                    $icon => view('filament.admin.workflow-buttons.icon-option', compact('icon', 'label'))->render(),
                                ])->all())
                            ->in(fn (): array => array_keys(WorkflowButton::iconOptions()))
                            ->allowHtml()
                            ->searchable()
                            ->searchValues()
                            ->searchPrompt(__('workflow_buttons.form.icon_search_placeholder'))
                            ->optionsLimit(150)
                            ->default('heroicon-o-cursor-arrow-rays')
                            ->required()
                            ->native(false),

                        Select::make('color')
                            ->label(__('workflow_buttons.fields.color'))
                            ->placeholder(__('workflow_buttons.placeholders.color'))
                            ->helperText(__('workflow_buttons.helpers.color'))
                            ->options(fn (): array => collect(WorkflowButton::colorOptions())
                                ->mapWithKeys(fn (string $label, string $color): array => [
                                    $color => view('filament.admin.workflow-buttons.color-option', compact('color', 'label'))->render(),
                                ])->all())
                            ->in(fn (): array => array_keys(WorkflowButton::colorOptions()))
                            ->allowHtml()
                            ->searchable()
                            ->searchValues()
                            ->searchPrompt(__('workflow_buttons.form.color_search_placeholder'))
                            ->default('primary')
                            ->required()
                            ->native(false),

                        Toggle::make('is_active')
                            ->label(__('workflow_buttons.fields.is_active'))
                            ->default(true),
                    ]),
            ]);
    }
}
