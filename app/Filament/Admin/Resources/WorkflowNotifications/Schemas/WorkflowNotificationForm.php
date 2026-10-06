<?php

namespace App\Filament\Admin\Resources\WorkflowNotifications\Schemas;

use App\Enums\WorkflowStageType;
use App\Models\Role;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;

class WorkflowNotificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('workflow_notifications.sections.overview'))
                    ->description(__('workflow_notifications.sections.overview_description'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('workflow_notifications.fields.name'))
                            ->placeholder(__('workflow_notifications.placeholders.name'))
                            ->required()
                            ->maxLength(255),

                    ]),

                Section::make(__('workflow_notifications.sections.stages'))
                    ->key('stages-section')
                    ->description(__('workflow_notifications.sections.stages_description'))
                    ->schema([
                        Builder::make('stages')
                            ->hiddenLabel()
                            ->blocks([
                                self::stageBlock(),
                            ])
                            ->default(fn (): array => [(string) Str::uuid() => ['type' => 'stage', 'data' => self::emptyStage()]])
                            ->addable(false)
                            ->reorderableWithButtons()
                            ->blockNumbers(false)
                            ->minItems(1)
                            ->required()
                            ->validationMessages([
                                'required' => __('workflow_notifications.validation.stages_required'),
                                'min' => __('workflow_notifications.validation.stages_required'),
                            ]),
                    ])
                    ->footerActions([
                        Action::make('addStage')
                            ->label(__('workflow_notifications.actions.add_stage'))
                            ->icon('heroicon-m-plus')
                            ->color('gray')
                            ->action(fn (Get $get, Set $set) => self::append($get, $set, 'stage', self::emptyStage())),

                    ])
                    ->footerActionsAlignment(Alignment::Center)
                    ->hidden(fn (string $operation): bool => $operation === 'view'),

                // The view page shows the same stages without the add buttons.
                Section::make(__('workflow_notifications.sections.stages'))
                    ->schema([
                        Builder::make('stages')
                            ->hiddenLabel()
                            ->blocks([
                                self::stageBlock(),
                            ])
                            ->blockNumbers(false),
                    ])
                    ->visible(fn (string $operation): bool => $operation === 'view'),
            ]);
    }

    private static function stageBlock(): Block
    {
        return Block::make('stage')
            ->label(fn (?array $state): string|HtmlString => filled($state['stage_name'] ?? null)
                ? (string) $state['stage_name']
                : new HtmlString('&nbsp;'))
            ->icon('heroicon-o-flag')
            ->schema(self::stageFields());
    }

    /**
     * Fields of one workflow stage.
     * Role and messages appear once the stage is handled by staff.
     */
    private static function stageFields(): array
    {
        $handledByStaff = fn (Get $get): bool => (bool) WorkflowStageType::tryFrom((string) $get('stage_type'))?->requiresRole();

        return [
            Grid::make(2)->schema([
                TextInput::make('stage_name')
                    ->label(__('workflow_notifications.fields.stage_name'))
                    ->placeholder(__('workflow_notifications.placeholders.stage_name'))
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true),

                Select::make('stage_type')
                    ->label(__('workflow_notifications.fields.stage_type'))
                    ->options(fn (): array => WorkflowStageType::options())
                    ->required()
                    ->live()
                    ->native(false),

                Select::make('responsible_role')
                    ->label(__('workflow_notifications.fields.responsible_role'))
                    ->options(fn (): array => Role::query()
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Role $role): array => [$role->name => $role->localized_name])
                        ->all())
                    ->searchable()
                    ->required($handledByStaff)
                    ->visible($handledByStaff)
                    ->columnSpanFull(),

                TextInput::make('status_message')
                    ->label(__('workflow_notifications.fields.status_message'))
                    ->placeholder(__('workflow_notifications.placeholders.status_message'))
                    ->maxLength(255)
                    ->visible($handledByStaff),

                Textarea::make('notification_message')
                    ->label(__('workflow_notifications.fields.notification_message'))
                    ->placeholder(__('workflow_notifications.placeholders.notification_message'))
                    ->rows(1)
                    ->autosize()
                    ->maxLength(1000)
                    ->visible($handledByStaff),
            ]),
        ];
    }

    private static function emptyStage(): array
    {
        return [
            'stage_name' => null,
            'stage_type' => null,
            'responsible_role' => null,
            'status_message' => null,
            'notification_message' => null,
        ];
    }

    private static function append(Get $get, Set $set, string $type, array $data): void
    {
        $items = $get('stages') ?? [];
        $items[(string) Str::uuid()] = ['type' => $type, 'data' => $data];

        $set('stages', $items);
    }
}
