<?php

namespace App\Filament\Admin\Resources\WorkflowNotifications\Schemas;

use App\Enums\WorkflowStageType;
use App\Models\Role;
use App\Support\WorkflowNotificationStageSummary;
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
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

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
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'unique' => __('workflow_notifications.validation.name_already_exists'),
                            ])
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

                // The view page uses a dedicated read-only stage summary.
                Section::make(__('workflow_notifications.sections.stages'))
                    ->schema([
                        View::make('filament.admin.workflow-notifications.stage-summary')
                            ->viewData(fn (Get $get): array => [
                                'stages' => WorkflowNotificationStageSummary::from($get('stages') ?? []),
                            ]),
                    ])
                    ->visible(fn (string $operation): bool => $operation === 'view'),
            ]);
    }

    private static function stageBlock(): Block
    {
        return Block::make('stage')
            ->label(fn (?array $state): string|HtmlString => filled($state['stage_name'] ?? null)
                ? WorkflowNotificationStageSummary::localizedName(
                    (string) $state['stage_name'],
                    WorkflowStageType::tryFrom((string) ($state['stage_type'] ?? null)),
                )
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
        $automaticRole = fn (Get $get): bool => WorkflowStageType::tryFrom((string) $get('stage_type')) === WorkflowStageType::Completed;
        $requiresManualRole = fn (Get $get): bool => $handledByStaff($get) && ! $automaticRole($get);

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
                    ->afterStateUpdated(function (?string $state, ?string $old, Get $get, Set $set): void {
                        $type = WorkflowStageType::tryFrom((string) $state);

                        if ($type !== null && blank($old) && blank($get('stage_name'))) {
                            $set('stage_name', $type->label());
                        }

                        // The role list depends on the stage type, so pick the role again.
                        $set('responsible_role', null);

                        if ($type === WorkflowStageType::FormSubmission) {
                            $set('status_message', null);
                            $set('notification_message', null);
                        }
                    })
                    ->native(false),

                Select::make('responsible_role')
                    ->label(__('workflow_notifications.fields.responsible_role'))
                    ->options(fn (Get $get): array => self::responsibleRoleOptions($get('stage_type')))
                    ->searchable()
                    ->preload()
                    ->in(fn (Get $get): array => array_keys(self::responsibleRoleOptions($get('stage_type'))))
                    ->required($requiresManualRole)
                    ->visible($requiresManualRole)
                    ->columnSpanFull(),

                TextInput::make('responsible_role_automatic')
                    ->label(__('workflow_notifications.fields.responsible_role'))
                    ->placeholder(__('workflow_notifications.placeholders.automatic_role'))
                    ->disabled()
                    ->dehydrated(false)
                    ->visible($automaticRole)
                    ->columnSpanFull(),

                TextInput::make('status_message')
                    ->label(__('workflow_notifications.fields.status_message'))
                    ->placeholder(fn (Get $get): string => filled($get('stage_type')) && Lang::has('workflow_notifications.status_defaults.'.$get('stage_type'))
                        ? __('workflow_notifications.status_defaults.'.$get('stage_type'))
                        : __('workflow_notifications.placeholders.status_message'))
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

    /**
     * Every role except the candidate role type, for stages handled by staff.
     */
    public static function responsibleRoleOptions(?string $stageType): array
    {
        $type = WorkflowStageType::tryFrom((string) $stageType);

        if (! $type?->requiresRole() || $type === WorkflowStageType::Completed) {
            return [];
        }

        return Role::query()
            ->where(fn ($query) => $query
                ->whereNull('role_type_key')
                ->orWhere('role_type_key', '!=', 'candidate'))
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Role $role): array => [$role->name => $role->localized_name])
            ->all();
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
