<?php

namespace App\Filament\Admin\Resources\WorkflowNotifications\Schemas;

use App\Enums\WorkflowPaymentSource;
use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\WorkflowStageTypes\WorkflowStageTypeResource;
use App\Models\EmailTemplate;
use App\Models\SmsTemplate;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType as StageTypeCatalog;
use App\Services\AmsWorkflowRoles;
use App\Support\WorkflowNotificationStageSummary;
use App\Support\WorkflowStaffRecipients;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageTypeSettings;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;
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
                            ->rules([
                                fn (?WorkflowNotification $record): Closure => self::stageTypesRule($record),
                            ])
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

    private static function stageTypesRule(?WorkflowNotification $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            if (! is_array($value)) {
                return;
            }

            $types = self::stageTypes($value);
            // Preserve older templates when only their labels or messages are edited.
            if ($record?->exists && $types === self::stageTypes($record->stages ?? [])) {
                return;
            }

            $seen = [];
            foreach ($types as $type) {
                $stageType = WorkflowStageType::tryFrom($type);
                if ($stageType === null) {
                    $fail(__('workflow_notifications.validation.invalid_stage_type'));

                    return;
                }

                $behavior = $stageType === WorkflowStageType::Approval ? WorkflowStageType::Review->value : $type;

                if (isset($seen[$behavior])) {
                    $fail(__('workflow_notifications.validation.duplicate_stage_type', ['stage' => $stageType->label()]));

                    return;
                }

                $seen[$behavior] = true;
            }
        };
    }

    private static function stageTypes(array $stages): array
    {
        return array_values(array_map(function (mixed $stage): string {
            if (! is_array($stage)) {
                return '';
            }

            $data = is_array($stage['data'] ?? null) ? $stage['data'] : $stage;
            $canonical = is_string($data['stage_type'] ?? null) ? $data['stage_type'] : '';
            $selection = $data['stage_type_id'] ?? null;

            if (filled($selection)) {
                $definition = StageTypeCatalog::selected($selection);

                if ($definition !== null) {
                    return $definition->behavior;
                }

                if ($selection !== 'legacy:'.$canonical || ! in_array($canonical, ['approval', 'rejected'], true)) {
                    return '';
                }
            }

            return $canonical;
        }, $stages));
    }

    private static function catalogIdForSave(mixed $selection, ?WorkflowNotification $record): ?int
    {
        $definition = StageTypeCatalog::selected($selection);

        if ($definition?->is_system && $record?->exists) {
            foreach ((array) $record->getOriginal('stages') as $stage) {
                $data = is_array($stage['data'] ?? null) ? $stage['data'] : $stage;

                // A hydrated built-in selection should not add catalog metadata to a legacy stage.
                if (is_array($data) && blank($data['stage_type_id'] ?? null)
                    && ($data['stage_type'] ?? null) === $definition->behavior) {
                    return null;
                }
            }
        }

        return $definition?->getKey();
    }

    private static function updateStageType(?string $state, ?string $old, Get $get, Set $set, ?string $defaultName = null): void
    {
        $type = WorkflowStageType::tryFrom((string) $state);

        if ($type !== null && blank($get('stage_name')) && ($defaultName !== null || blank($old))) {
            $set('stage_name', $defaultName ?? $type->label());
        }

        // Choosing another label for the same action must keep its existing settings.
        if ($state === $old) {
            return;
        }

        $set('responsible_role', []);

        if ($type === WorkflowStageType::Payment && blank($get('payment_source'))) {
            $set('payment_source', WorkflowPaymentSource::System->value);
        }

        if ($type === WorkflowStageType::FormSubmission) {
            $set('status_message', null);
            $set('notification_message', null);
            $set('review_actions', null);
        }
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
     * Submission messages are optional; staff-handled stages retain their role and message settings.
     */
    private static function stageFields(): array
    {
        $handledByStaff = fn (Get $get): bool => (bool) WorkflowStageType::tryFrom((string) $get('stage_type'))?->requiresRole();
        $isCompletedStage = fn (Get $get): bool => WorkflowStageType::tryFrom((string) $get('stage_type')) === WorkflowStageType::Completed;
        $requiresManualRole = fn (Get $get): bool => $handledByStaff($get) && ! $isCompletedStage($get);
        $isReviewStage = fn (Get $get): bool => WorkflowStageType::tryFrom((string) $get('stage_type')) === WorkflowStageType::Review;
        $isPaymentStage = fn (Get $get): bool => WorkflowStageType::tryFrom((string) $get('stage_type')) === WorkflowStageType::Payment;
        $isAwaitingResultsStage = fn (Get $get): bool => WorkflowStageType::tryFrom((string) $get('stage_type')) === WorkflowStageType::AwaitingResults;
        $isSubmissionStage = fn (Get $get): bool => WorkflowStageType::tryFrom((string) $get('stage_type')) === WorkflowStageType::FormSubmission;
        $usesStageAudienceLayout = fn (Get $get): bool => in_array(WorkflowStageType::tryFrom((string) $get('stage_type')), [
            WorkflowStageType::Payment,
            WorkflowStageType::AwaitingResults,
            WorkflowStageType::Completed,
        ], true);
        $supportsNotificationChannels = fn (Get $get): bool => $handledByStaff($get) || $isSubmissionStage($get);

        return [
            Grid::make(2)->schema([
                CheckboxList::make('notification_channels')
                    ->label(fn (): HtmlString => new HtmlString('<span style="font-size: 1.05rem; font-weight: 700;">'.e(__('workflow_notifications.fields.notification_channels')).'</span>'))
                    ->options(fn (): array => collect(WorkflowStageMessages::CHANNELS)
                        ->mapWithKeys(fn (string $channel): array => [$channel => '<span style="font-size: 1.05rem;">'.e(__('workflow_notifications.channels.'.$channel)).'</span>'])
                        ->all())
                    ->allowHtml()
                    ->default(['system'])
                    ->columns(3)
                    ->gridDirection('row')
                    ->live()
                    ->visible($supportsNotificationChannels)
                    ->columnSpanFull(),

                Select::make('email_template_key')
                    ->label(__('workflow_notifications.fields.email_template'))
                    ->placeholder(__('workflow_notifications.placeholders.plain_email'))
                    ->options(fn (): array => EmailTemplate::query()
                        ->whereNotIn('key', EmailTemplate::TEMPLATES)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (EmailTemplate $template): array => [$template->key => $template->label()])
                        ->all())
                    ->searchable()
                    ->native(false)
                    ->visible(fn (Get $get): bool => $supportsNotificationChannels($get) && in_array('email', (array) $get('notification_channels'), true)),

                Select::make('sms_template_key')
                    ->label(__('workflow_notifications.fields.sms_template'))
                    ->placeholder(__('workflow_notifications.placeholders.plain_sms'))
                    ->options(fn (): array => SmsTemplate::query()
                        ->whereNotIn('key', SmsTemplate::TEMPLATES)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (SmsTemplate $template): array => [$template->key => $template->label()])
                        ->all())
                    ->searchable()
                    ->native(false)
                    ->visible(fn (Get $get): bool => $supportsNotificationChannels($get) && in_array('sms', (array) $get('notification_channels'), true)),

                TextInput::make('stage_name')
                    ->label(__('workflow_notifications.fields.stage_name'))
                    ->placeholder(__('workflow_notifications.placeholders.stage_name'))
                    ->formatStateUsing(fn (?string $state, Get $get): ?string => $state === 'ការពិនិត្យ'
                        ? WorkflowNotificationStageSummary::localizedName($state, WorkflowStageType::tryFrom((string) $get('stage_type')))
                        : $state)
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true),

                Select::make('stage_type_id')
                    ->label(__('workflow_notifications.fields.stage_type'))
                    ->options(fn (Get $get): array => StageTypeCatalog::selectOptions($get('stage_type_id'), $get('stage_type')))
                    ->afterStateHydrated(function (Select $component, mixed $state, Get $get): void {
                        if (blank($state)) {
                            $component->state(StageTypeCatalog::selectionForBehavior($get('stage_type')));
                        }
                    })
                    ->dehydrateStateUsing(fn (mixed $state, ?WorkflowNotification $record): ?int => self::catalogIdForSave($state, $record))
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                        $definition = StageTypeCatalog::selected($state);
                        $behavior = $definition?->behavior;

                        if ($definition === null || $behavior === null) {
                            return;
                        }

                        $old = $get('stage_type');
                        $set('stage_type', $behavior);
                        self::updateStageType($behavior, $old, $get, $set, $definition->label());

                        if ($old !== $behavior) {
                            $set('enabled_actions', $definition->enabled_actions);
                        }

                        $defaults = $definition->stageDefaults($get('payment_source'));
                        $current = array_map(fn (string $key): mixed => $get($key), array_diff(array_keys($defaults), ['enabled_actions']));

                        if ($old !== $behavior && ! StageTypeCatalog::hasReviewActionSettings($current)) {
                            self::applyStageTypeDefaults($definition, $get, $set);
                        }
                    })
                    ->suffixAction(
                        Action::make('manageStageTypes')
                            ->label(__('workflow_stage_types.navigation_label'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->url(fn (): string => WorkflowStageTypeResource::getUrl('index'))
                            ->openUrlInNewTab()
                            ->visible(fn (): bool => WorkflowStageTypeResource::canViewAny()),
                    )
                    ->native(false),

                Hidden::make('enabled_actions')
                    ->dehydrated(fn (mixed $state, Get $get, ?WorkflowNotification $record): bool => $state !== null
                        || (! $record?->exists && StageTypeCatalog::selected($get('stage_type_id'))?->enabled_actions !== null)),

                Select::make('stage_type')
                    ->label(__('workflow_notifications.fields.stage_type'))
                    ->options(fn (): array => WorkflowStageType::options())
                    ->required()
                    ->hidden()
                    ->dehydratedWhenHidden()
                    ->live()
                    ->afterStateUpdated(function (?string $state, ?string $old, Get $get, Set $set): void {
                        if (StageTypeCatalog::selected($get('stage_type_id'))?->behavior !== $state) {
                            $set('stage_type_id', StageTypeCatalog::selectionForBehavior($state));
                        }

                        if ($state !== $old) {
                            $set('enabled_actions', StageTypeCatalog::selected($get('stage_type_id'))?->enabled_actions);
                        }

                        self::updateStageType($state, $old, $get, $set);
                    })
                    ->native(false),

                Select::make('payment_source')
                    ->label(__('workflow_notifications.fields.payment_source'))
                    ->options(fn (): array => WorkflowPaymentSource::options())
                    ->in(fn (): array => array_keys(WorkflowPaymentSource::options()))
                    ->default(WorkflowPaymentSource::System->value)
                    ->afterStateHydrated(fn (Select $component, mixed $state) => $component->state(WorkflowPaymentSource::resolve($state)->value))
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('responsible_role', []);
                        $set('audience_messages.staff_recipient_roles', []);
                    })
                    ->required($isPaymentStage)
                    ->visible($isPaymentStage)
                    ->native(false)
                    ->columnSpanFull(),

                TextInput::make('submission_messages.candidate_status_message')
                    ->label(__('workflow_notifications.fields.shared_status_message'))
                    ->placeholder(__('workflow_notifications.placeholders.submission_status'))
                    ->maxLength(255)
                    ->visible($isSubmissionStage)
                    ->columnSpan(1),

                TextInput::make('status_message')
                    ->label(fn (Get $get): string => __($isPaymentStage($get)
                        ? 'workflow_notifications.fields.paid_status_message'
                        : ($isAwaitingResultsStage($get)
                            ? 'workflow_notifications.fields.passed_result_status_message'
                            : ($usesStageAudienceLayout($get)
                                ? 'workflow_notifications.fields.shared_status_message'
                                : 'workflow_notifications.fields.status_message'))))
                    ->helperText(fn (Get $get): ?string => $isAwaitingResultsStage($get)
                        ? __('workflow_notifications.helpers.passed_result_status_message')
                        : null)
                    ->placeholder(fn (Get $get): string => $usesStageAudienceLayout($get)
                        ? __('workflow_notifications.placeholders.stage_audiences.'.$get('stage_type').'.status_message')
                        : (filled($get('stage_type')) && Lang::has('workflow_notifications.status_defaults.'.$get('stage_type'))
                            ? __('workflow_notifications.status_defaults.'.$get('stage_type'))
                            : __('workflow_notifications.placeholders.status_message')))
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $handledByStaff($get) && ! $isReviewStage($get))
                    ->columnSpan(1),

                TextInput::make('alert_message')
                    ->label(__('workflow_notifications.fields.alert_message'))
                    ->helperText(fn (Get $get): ?string => self::isAmsPayment($get) ? __('workflow_notifications.helpers.ams_alert') : null)
                    ->placeholder(__('workflow_notifications.placeholders.alert_message'))
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => filled($get('stage_type')) && ! $isReviewStage($get))
                    ->columnSpan(1),

                Select::make('responsible_role')
                    ->label(__('workflow_notifications.fields.responsible_role'))
                    ->options(fn (Get $get): array => self::stageRoleOptions($get))
                    ->helperText(fn (Get $get): ?string => self::amsRoleHelp($get))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->in(fn (Get $get): array => array_keys(self::stageRoleOptions($get)))
                    ->required($requiresManualRole)
                    ->visible($handledByStaff)
                    ->columnSpanFull(),

                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        Group::make()->schema([
                            Placeholder::make('submission_candidate_heading')
                                ->content(new HtmlString('<div class="border-b border-gray-200 pb-3 font-semibold dark:border-gray-700">'.e(__('workflow_notifications.sections.for_candidates')).'</div>'))
                                ->hiddenLabel(),

                            Textarea::make('submission_messages.candidate_notification_message')
                                ->label(__('workflow_notifications.fields.notification_message'))
                                ->placeholder(__('workflow_notifications.placeholders.submission_candidate_notification'))
                                ->rows(1)
                                ->autosize()
                                ->maxLength(1000),
                        ])->columnSpan(1),

                        Group::make()->schema([
                            Placeholder::make('submission_staff_heading')
                                ->content(new HtmlString('<div class="border-b border-gray-200 pb-3 font-semibold dark:border-gray-700">'.e(__('workflow_notifications.sections.for_system_users')).'</div>'))
                                ->hiddenLabel(),

                            Textarea::make('submission_messages.staff_notification_message')
                                ->label(__('workflow_notifications.fields.staff_notification_message'))
                                ->placeholder(__('workflow_notifications.placeholders.submission_staff_notification'))
                                ->helperText(__('workflow_notifications.helpers.staff_message_placeholders'))
                                ->rows(1)
                                ->autosize()
                                ->maxLength(1000),

                            Select::make('submission_messages.staff_recipient_roles')
                                ->label(__('workflow_notifications.fields.staff_recipient_roles'))
                                ->placeholder(__('workflow_notifications.placeholders.staff_recipient_roles'))
                                ->options(fn (): array => WorkflowStaffRecipients::roleOptions())
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->in(fn (): array => array_keys(WorkflowStaffRecipients::roleOptions()))
                                ->default([])
                                ->native(false),
                        ])->columnSpan(1),
                    ])
                    ->visible($isSubmissionStage)
                    ->columnSpanFull(),

                Grid::make(1)
                    ->columns(fn (Get $get): array => ['default' => 1, 'lg' => $usesStageAudienceLayout($get) ? 2 : 1])
                    ->schema([
                        Group::make()->schema([
                            Placeholder::make('stage_candidate_heading')
                                ->content(new HtmlString('<div class="border-b border-gray-200 pb-3 font-semibold dark:border-gray-700">'.e(__('workflow_notifications.sections.for_candidates')).'</div>'))
                                ->hiddenLabel()
                                ->visible($usesStageAudienceLayout),

                            Textarea::make('notification_message')
                                ->label(__('workflow_notifications.fields.notification_message'))
                                ->placeholder(fn (Get $get): string => $usesStageAudienceLayout($get)
                                    ? __('workflow_notifications.placeholders.stage_audiences.'.$get('stage_type').'.notification_message')
                                    : __('workflow_notifications.placeholders.notification_message'))
                                ->rows(2)
                                ->autosize()
                                ->maxLength(1000),
                        ])->columnSpan(1),

                        Group::make()->schema([
                            Placeholder::make('stage_staff_heading')
                                ->content(fn (Get $get): HtmlString => new HtmlString('<div class="border-b border-gray-200 pb-3 font-semibold dark:border-gray-700">'.e(__(self::isAmsPayment($get) ? 'workflow_notifications.sections.for_ams_users' : 'workflow_notifications.sections.for_system_users')).'</div>'))
                                ->hiddenLabel(),

                            TextInput::make('payment_statuses.unpaid_status_message')
                                ->label(__('workflow_notifications.fields.unpaid_status_message'))
                                ->placeholder(__('workflow_notifications.placeholders.unpaid_status_message'))
                                ->maxLength(255)
                                ->visible($isPaymentStage),

                            Grid::make(['default' => 1, 'lg' => 2])
                                ->schema([
                                    TextInput::make('result_statuses.pending_status_message')
                                        ->label(__('workflow_notifications.fields.waiting_result_status_message'))
                                        ->placeholder(__('workflow_notifications.placeholders.waiting_result_status_message'))
                                        ->maxLength(255),

                                    TextInput::make('result_statuses.pending_alert_message')
                                        ->label(__('workflow_notifications.fields.pending_alert_message'))
                                        ->placeholder(__('workflow_notifications.placeholders.pending_alert_message'))
                                        ->maxLength(255),
                                ])
                                ->visible($isAwaitingResultsStage),

                            Textarea::make('audience_messages.staff_notification_message')
                                ->label(__('workflow_notifications.fields.staff_notification_message'))
                                ->placeholder(fn (Get $get): string => __('workflow_notifications.placeholders.stage_audiences.'.$get('stage_type').'.staff_notification_message'))
                                ->helperText(__('workflow_notifications.helpers.staff_message_placeholders'))
                                ->rows(2)
                                ->autosize()
                                ->maxLength(1000),

                            Select::make('audience_messages.staff_recipient_roles')
                                ->label(__('workflow_notifications.fields.staff_recipient_roles'))
                                ->placeholder(__('workflow_notifications.placeholders.staff_recipient_roles'))
                                ->options(fn (Get $get): array => self::stageRecipientRoleOptions($get))
                                ->helperText(fn (Get $get): ?string => self::amsRoleHelp($get))
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->in(fn (Get $get): array => array_keys(self::stageRecipientRoleOptions($get)))
                                ->default([])
                                ->native(false),
                        ])->visible($usesStageAudienceLayout)->columnSpan(1),
                    ])
                    ->visible(fn (Get $get): bool => $handledByStaff($get) && ! $isReviewStage($get))
                    ->columnSpanFull(),

                Section::make(__('workflow_notifications.sections.review_outcomes'))
                    ->key('review-actions')
                    ->compact()
                    ->columns(1)
                    ->schema([
                        self::reviewActionTabs(),
                    ])
                    ->footerActions([
                        Action::make('applyStageTypeReviewSettings')
                            ->label(__('workflow_stage_types.actions.apply_review_settings'))
                            ->tooltip(__('workflow_stage_types.helpers.apply_review_settings'))
                            ->icon('heroicon-o-arrow-path')
                            ->color('gray')
                            ->visible(function (Get $get): bool {
                                $definition = StageTypeCatalog::selected($get('stage_type_id'));

                                return $definition?->behavior === WorkflowStageType::Review->value
                                    && $definition->stageDefaults() !== [];
                            })
                            ->action(function (Get $get, Set $set): void {
                                $definition = StageTypeCatalog::selected($get('stage_type_id'));

                                if ($definition?->behavior === WorkflowStageType::Review->value) {
                                    self::applyStageTypeDefaults($definition, $get, $set);
                                }
                            }),
                    ])
                    ->visible($isReviewStage)
                    ->columnSpanFull(),
            ]),
        ];
    }

    private static function applyStageTypeDefaults(StageTypeCatalog $definition, Get $get, Set $set): void
    {
        $defaults = $definition->stageDefaults($get('payment_source'));

        $set('enabled_actions', $definition->enabled_actions);

        foreach (array_keys(WorkflowStageTypeSettings::actions((string) $definition->behavior)) as $action) {
            foreach (WorkflowStageTypeSettings::fields((string) $definition->behavior, $action) as $field) {
                if (Arr::has($defaults, $field['path'])) {
                    $set($field['path'], Arr::get($defaults, $field['path']));
                }
            }
        }
    }

    private static function reviewActionTabs(): Tabs
    {
        return Tabs::make(__('workflow_notifications.sections.review_outcomes'))
            ->key(fn (Get $get): string => 'review-action-tabs-'.implode('-', array_keys(WorkflowStageTypeSettings::selectedActions('review', $get('enabled_actions')))))
            ->tabs(function (Get $get): array {
                $tabs = [];
                foreach (WorkflowStageTypeSettings::selectedActions('review', $get('enabled_actions')) as $key => $action) {
                    $tabs[] = Tab::make($action['label'])
                        ->key('review-action-tab-'.$key)
                        ->icon($action['icon'])
                        ->schema([self::reviewActionGroup($key)]);
                }

                return $tabs;
            });
    }

    private static function reviewActionGroup(string $action): Group
    {
        return Group::make()
            ->key('review-action-'.$action)
            ->extraAttributes($action === 'accept' ? [] : ['class' => 'border-t border-gray-200 pt-6 dark:border-gray-700'])
            ->columns(1)
            ->schema([
                Placeholder::make('review_actions.'.$action.'.heading')
                    ->content(new HtmlString('<div class="font-semibold">'.e(__('workflow_notifications.review_actions.'.$action.'.title')).'</div>'))
                    ->hiddenLabel(),

                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('review_actions.'.$action.'.status_message')
                            ->label(__('workflow_notifications.fields.shared_status_message'))
                            ->placeholder(__('workflow_notifications.placeholders.review_actions.'.$action.'.status_message'))
                            ->maxLength(255),

                        TextInput::make('review_actions.'.$action.'.alert_message')
                            ->label(__('workflow_notifications.fields.alert_message'))
                            ->placeholder(__('workflow_notifications.placeholders.alert_message'))
                            ->maxLength(255),
                    ]),

                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        Group::make()->schema([
                            Placeholder::make('review_actions.'.$action.'.candidate_heading')
                                ->content(new HtmlString('<div class="border-b border-gray-200 pb-2 font-semibold dark:border-gray-700">'.e(__('workflow_notifications.sections.for_candidates')).'</div>'))
                                ->hiddenLabel(),

                            Textarea::make('review_actions.'.$action.'.notification_message')
                                ->label(__('workflow_notifications.review_actions.'.$action.'.notification_message'))
                                ->placeholder(__('workflow_notifications.placeholders.review_actions.'.$action.'.notification_message'))
                                ->rows(2)
                                ->autosize()
                                ->maxLength(1000),
                        ])->columnSpan(1),

                        Group::make()->schema([
                            Placeholder::make('review_actions.'.$action.'.staff_heading')
                                ->content(new HtmlString('<div class="border-b border-gray-200 pb-2 font-semibold dark:border-gray-700">'.e(__('workflow_notifications.sections.for_system_users')).'</div>'))
                                ->hiddenLabel(),

                            Textarea::make('review_actions.'.$action.'.staff_notification_message')
                                ->label(__('workflow_notifications.fields.staff_notification_message'))
                                ->placeholder(__('workflow_notifications.placeholders.review_actions.'.$action.'.staff_notification_message'))
                                ->helperText(__('workflow_notifications.helpers.staff_message_placeholders'))
                                ->rows(2)
                                ->autosize()
                                ->maxLength(1000),

                            Select::make('review_actions.'.$action.'.staff_recipient_roles')
                                ->label(__('workflow_notifications.fields.staff_recipient_roles'))
                                ->placeholder(__('workflow_notifications.placeholders.staff_recipient_roles'))
                                ->options(fn (): array => WorkflowStaffRecipients::roleOptions())
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->in(fn (): array => array_keys(WorkflowStaffRecipients::roleOptions()))
                                ->default([])
                                ->native(false),
                        ])->columnSpan(1),
                    ]),
            ]);
    }

    private static function isAmsPayment(Get $get): bool
    {
        return $get('stage_type') === WorkflowStageType::Payment->value
            && WorkflowPaymentSource::resolve($get('payment_source')) === WorkflowPaymentSource::Ams;
    }

    private static function stageRoleOptions(Get $get): array
    {
        return self::isAmsPayment($get)
            ? AmsWorkflowRoles::options()
            : self::responsibleRoleOptions($get('stage_type'));
    }

    private static function stageRecipientRoleOptions(Get $get): array
    {
        return self::isAmsPayment($get)
            ? AmsWorkflowRoles::options()
            : WorkflowStaffRecipients::roleOptions();
    }

    private static function amsRoleHelp(Get $get): ?string
    {
        if (! self::isAmsPayment($get)) {
            return null;
        }

        return __(AmsWorkflowRoles::options() === []
            ? 'workflow_notifications.helpers.ams_roles_unavailable'
            : 'workflow_notifications.helpers.ams_roles');
    }

    /**
     * Every role except the candidate role type, for stages handled by staff.
     */
    public static function responsibleRoleOptions(?string $stageType): array
    {
        $type = WorkflowStageType::tryFrom((string) $stageType);

        if (! $type?->requiresRole()) {
            return [];
        }

        return WorkflowStaffRecipients::roleOptions();
    }

    private static function emptyStage(): array
    {
        return [
            'stage_name' => null,
            'stage_type_id' => null,
            'stage_type' => null,
            'responsible_role' => [],
            'status_message' => null,
            'alert_message' => null,
            'notification_message' => null,
            'review_actions' => [],
            'notification_channels' => ['system'],
        ];
    }

    private static function append(Get $get, Set $set, string $type, array $data): void
    {
        $items = $get('stages') ?? [];
        $items[(string) Str::uuid()] = ['type' => $type, 'data' => $data];

        $set('stages', $items);
    }
}
