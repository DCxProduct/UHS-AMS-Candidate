<?php

namespace App\Filament\Admin\Resources\WorkflowNotifications\Schemas;

use App\Enums\WorkflowPaymentSource;
use App\Enums\WorkflowStageType;
use App\Models\EmailTemplate;
use App\Models\Role;
use App\Models\SmsTemplate;
use App\Support\WorkflowNotificationStageSummary;
use App\Support\WorkflowStaffRecipients;
use App\Support\WorkflowStageMessages;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
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
                    ->helperText(__('workflow_notifications.helpers.notification_channels'))
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

                        $set('responsible_role', []);

                        if ($type === WorkflowStageType::Payment && blank($get('payment_source'))) {
                            $set('payment_source', WorkflowPaymentSource::System->value);
                        }

                        if ($type === WorkflowStageType::FormSubmission) {
                            $set('status_message', null);
                            $set('notification_message', null);
                            $set('review_actions', null);
                        }
                    })
                    ->native(false),

                Select::make('payment_source')
                    ->label(__('workflow_notifications.fields.payment_source'))
                    ->helperText(__('workflow_notifications.helpers.payment_source'))
                    ->options(fn (): array => WorkflowPaymentSource::options())
                    ->in(fn (): array => array_keys(WorkflowPaymentSource::options()))
                    ->default(WorkflowPaymentSource::System->value)
                    ->afterStateHydrated(fn (Select $component, mixed $state) => $component->state(WorkflowPaymentSource::resolve($state)->value))
                    ->required($isPaymentStage)
                    ->visible($isPaymentStage)
                    ->native(false)
                    ->columnSpanFull(),

                TextInput::make('submission_messages.candidate_status_message')
                    ->label(__('workflow_notifications.fields.shared_status_message'))
                    ->placeholder(__('workflow_notifications.placeholders.submission_status'))
                    ->helperText(__('workflow_notifications.helpers.submission_status'))
                    ->maxLength(255)
                    ->visible($isSubmissionStage)
                    ->columnSpanFull(),

                TextInput::make('payment_statuses.unpaid_status_message')
                    ->label(__('workflow_notifications.fields.unpaid_status_message'))
                    ->placeholder(__('workflow_notifications.placeholders.unpaid_status_message'))
                    ->helperText(__('workflow_notifications.helpers.unpaid_status_message'))
                    ->maxLength(255)
                    ->visible($isPaymentStage),

                TextInput::make('result_statuses.pending_status_message')
                    ->label(__('workflow_notifications.fields.waiting_result_status_message'))
                    ->placeholder(__('workflow_notifications.placeholders.waiting_result_status_message'))
                    ->helperText(__('workflow_notifications.helpers.waiting_result_status_message'))
                    ->maxLength(255)
                    ->visible($isAwaitingResultsStage),

                TextInput::make('status_message')
                    ->label(fn (Get $get): string => __($isPaymentStage($get)
                        ? 'workflow_notifications.fields.paid_status_message'
                        : ($isAwaitingResultsStage($get)
                            ? 'workflow_notifications.fields.passed_result_status_message'
                            : ($usesStageAudienceLayout($get)
                                ? 'workflow_notifications.fields.shared_status_message'
                                : 'workflow_notifications.fields.status_message'))))
                    ->helperText(fn (Get $get): ?string => $isPaymentStage($get)
                        ? __('workflow_notifications.helpers.paid_status_message')
                        : ($isAwaitingResultsStage($get)
                            ? __('workflow_notifications.helpers.passed_result_status_message')
                            : ($usesStageAudienceLayout($get) ? __('workflow_notifications.helpers.shared_stage_status') : null)))
                    ->placeholder(fn (Get $get): string => $usesStageAudienceLayout($get)
                        ? __('workflow_notifications.placeholders.stage_audiences.'.$get('stage_type').'.status_message')
                        : (filled($get('stage_type')) && Lang::has('workflow_notifications.status_defaults.'.$get('stage_type'))
                            ? __('workflow_notifications.status_defaults.'.$get('stage_type'))
                            : __('workflow_notifications.placeholders.status_message')))
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $handledByStaff($get) && ! $isReviewStage($get))
                    ->columnSpan(fn (Get $get): string|int => ($isPaymentStage($get) || $isAwaitingResultsStage($get)) ? 1 : 'full'),

                Select::make('responsible_role')
                    ->label(__('workflow_notifications.fields.responsible_role'))
                    ->helperText(fn (Get $get): ?string => $isCompletedStage($get) ? __('workflow_notifications.helpers.completed_responsible_role') : null)
                    ->options(fn (Get $get): array => self::responsibleRoleOptions($get('stage_type')))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->in(fn (Get $get): array => array_keys(self::responsibleRoleOptions($get('stage_type'))))
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
                                ->helperText(__('workflow_notifications.helpers.submission_candidate_notification'))
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
                                ->rows(1)
                                ->autosize()
                                ->maxLength(1000),

                            Select::make('submission_messages.staff_recipient_roles')
                                ->label(__('workflow_notifications.fields.staff_recipient_roles'))
                                ->placeholder(__('workflow_notifications.placeholders.staff_recipient_roles'))
                                ->helperText(__('workflow_notifications.helpers.review_staff_recipients'))
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
                                ->helperText(fn (Get $get): ?string => $usesStageAudienceLayout($get) ? __('workflow_notifications.helpers.stage_candidate_notification') : null)
                                ->placeholder(fn (Get $get): string => $usesStageAudienceLayout($get)
                                    ? __('workflow_notifications.placeholders.stage_audiences.'.$get('stage_type').'.notification_message')
                                    : __('workflow_notifications.placeholders.notification_message'))
                                ->rows(2)
                                ->autosize()
                                ->maxLength(1000),
                        ])->columnSpan(1),

                        Group::make()->schema([
                            Placeholder::make('stage_staff_heading')
                                ->content(new HtmlString('<div class="border-b border-gray-200 pb-3 font-semibold dark:border-gray-700">'.e(__('workflow_notifications.sections.for_system_users')).'</div>'))
                                ->hiddenLabel(),

                            Textarea::make('audience_messages.staff_notification_message')
                                ->label(__('workflow_notifications.fields.staff_notification_message'))
                                ->helperText(__('workflow_notifications.helpers.stage_staff_notification'))
                                ->placeholder(fn (Get $get): string => __('workflow_notifications.placeholders.stage_audiences.'.$get('stage_type').'.staff_notification_message'))
                                ->rows(2)
                                ->autosize()
                                ->maxLength(1000),

                            Select::make('audience_messages.staff_recipient_roles')
                                ->label(__('workflow_notifications.fields.staff_recipient_roles'))
                                ->placeholder(__('workflow_notifications.placeholders.staff_recipient_roles'))
                                ->helperText(__('workflow_notifications.helpers.review_staff_recipients'))
                                ->options(fn (): array => WorkflowStaffRecipients::roleOptions())
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->in(fn (): array => array_keys(WorkflowStaffRecipients::roleOptions()))
                                ->default([])
                                ->native(false),
                        ])->visible($usesStageAudienceLayout)->columnSpan(1),
                    ])
                    ->visible(fn (Get $get): bool => $handledByStaff($get) && ! $isReviewStage($get))
                    ->columnSpanFull(),

                Section::make(__('workflow_notifications.sections.review_outcomes'))
                    ->key('review-actions')
                    ->description(__('workflow_notifications.helpers.review_outcomes'))
                    ->compact()
                    ->columns(1)
                    ->schema([
                        self::reviewActionGroup('accept'),
                        self::reviewActionGroup('send_back'),
                        self::reviewActionGroup('reject'),
                    ])
                    ->visible($isReviewStage)
                    ->columnSpanFull(),
            ]),
        ];
    }

    private static function reviewActionGroup(string $action): Group
    {
        return Group::make()
            ->key('review-action-'.$action)
            ->extraAttributes($action === 'accept' ? [] : ['class' => 'border-t border-gray-200 pt-6 dark:border-gray-700'])
            ->columns(1)
            ->schema([
                Placeholder::make('review_actions.'.$action.'.heading')
                    ->content(new HtmlString('<div class="font-semibold">'.e(__('workflow_notifications.review_actions.'.$action.'.title')).'</div><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">'.e(__('workflow_notifications.review_actions.'.$action.'.description')).'</p>'))
                    ->hiddenLabel(),

                TextInput::make('review_actions.'.$action.'.status_message')
                    ->label(__('workflow_notifications.fields.shared_status_message'))
                    ->helperText(__('workflow_notifications.helpers.shared_review_status'))
                    ->placeholder(__('workflow_notifications.placeholders.review_actions.'.$action.'.status_message'))
                    ->maxLength(255),

                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        Group::make()->schema([
                            Placeholder::make('review_actions.'.$action.'.candidate_heading')
                                ->content(new HtmlString('<div class="border-b border-gray-200 pb-2 font-semibold dark:border-gray-700">'.e(__('workflow_notifications.sections.for_candidates')).'</div>'))
                                ->hiddenLabel(),

                            Textarea::make('review_actions.'.$action.'.notification_message')
                                ->label(__('workflow_notifications.review_actions.'.$action.'.notification_message'))
                                ->helperText(__('workflow_notifications.helpers.review_candidate_notification'))
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
                                ->helperText(__('workflow_notifications.helpers.review_staff_notification'))
                                ->placeholder(__('workflow_notifications.placeholders.review_actions.'.$action.'.staff_notification_message'))
                                ->rows(2)
                                ->autosize()
                                ->maxLength(1000),

                            Select::make('review_actions.'.$action.'.staff_recipient_roles')
                                ->label(__('workflow_notifications.fields.staff_recipient_roles'))
                                ->placeholder(__('workflow_notifications.placeholders.staff_recipient_roles'))
                                ->helperText(__('workflow_notifications.helpers.review_staff_recipients'))
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

    /**
     * Every role except the candidate role type, for stages handled by staff.
     */
    public static function responsibleRoleOptions(?string $stageType): array
    {
        $type = WorkflowStageType::tryFrom((string) $stageType);

        if (! $type?->requiresRole()) {
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
            'responsible_role' => [],
            'status_message' => null,
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
