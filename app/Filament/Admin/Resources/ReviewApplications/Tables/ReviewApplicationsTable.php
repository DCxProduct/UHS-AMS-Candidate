<?php

namespace App\Filament\Admin\Resources\ReviewApplications\Tables;

use App\Models\User;
use App\Support\NotificationLanguage;
use Carbon\Carbon;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class ReviewApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->checkIfRecordIsSelectableUsing(function (CustomFormEntry $record): bool {
                return strtolower((string) data_get($record->data, 'candidate_status', 'pending')) === 'pending';
            })
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('exam_results.no'))
                    ->rowIndex()
                    ->alignCenter(),

                TextColumn::make('form_type')
                    ->label(__('review_applications.form_type'))
                    ->getStateUsing(fn (CustomFormEntry $record): string => self::recordFormTypeLabel($record))
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('academic_year')
                    ->label(__('exam_results.academic_year'))
                    ->getStateUsing(fn ($record): string => self::entryValue($record, 'academic_year', $record->creator?->academic_year))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('data->academic_year', 'like', "%{$search}%"))
                    ->sortable(),

                TextColumn::make('seat_number')
                    ->label(__('exam_results.seat_number'))
                    ->getStateUsing(fn ($record): string => self::entryValue($record, 'seat_number', self::entryValue($record, 'list_number', $record->creator?->seat_number)))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('data->seat_number', 'like', "%{$search}%")
                        ->orWhere('data->list_number', 'like', "%{$search}%")),

                TextColumn::make('name_khmer')
                    ->label(__('exam_results.name_khmer'))
                    ->getStateUsing(fn ($record): string => self::khmerName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('data->first_name_kh', 'like', "%{$search}%")
                        ->orWhere('data->last_name_kh', 'like', "%{$search}%")),

                TextColumn::make('name_latin')
                    ->label(__('exam_results.name_latin'))
                    ->getStateUsing(fn ($record): string => self::latinName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('data->first_name_en', 'like', "%{$search}%")
                        ->orWhere('data->last_name_en', 'like', "%{$search}%")),

                TextColumn::make('gender')
                    ->label(__('exam_results.gender'))
                    ->getStateUsing(fn ($record): string => self::genderLabel(self::entryValue($record, 'gender'))),

                TextColumn::make('date_of_birth')
                    ->label(__('exam_results.date_of_birth'))
                    ->getStateUsing(fn ($record): string => self::dateValue(self::entryValue($record, 'date_of_birth', $record->creator?->date_of_birth))),

                TextColumn::make('data.candidate_status')
                    ->label(__('review_applications.review_status_result'))
                    ->badge()
                    ->getStateUsing(fn ($record) => data_get($record->data, 'candidate_status', 'pending'))
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'passed' => __('review_applications.statuses.passed'),
                        default => __('review_applications.statuses.pending'),
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'passed' => 'success',
                        default => 'warning',
                    }),

                TextColumn::make('data.candidate_reviewed_at')
                    ->label(__('review_applications.reviewed_at'))
                    ->getStateUsing(fn (CustomFormEntry $record): ?string => data_get($record->data, 'candidate_status') === 'passed'
                        ? data_get($record->data, 'candidate_reviewed_at')
                        : null)
                    ->formatStateUsing(fn ($state) => filled($state)
                        ? Carbon::parse($state)->format('d M Y H:i')
                        : '-')
                    ->color('info')
                    ->sortable(false),
            ])
            ->filters([
                Filter::make('application_review_filters')
                    ->label(new HtmlString('&nbsp;'))
                    ->schema([
                        Select::make('form_selection')
                            ->label(__('review_applications.form_type'))
                            ->options(fn (): array => self::dynamicFormTypeOptions())
                            ->native(false)
                            ->live(),

                        Select::make('review_status')
                            ->label(__('review_applications.review_status'))
                            ->options([
                                'pending' => self::statusLabel('pending'),
                                'passed' => self::statusLabel('passed'),
                            ])
                            ->native(false)
                            ->live(),

                        Select::make('reviewed_year')
                            ->label(__('review_applications.reviewed_year'))
                            ->options(fn (): array => self::dynamicRequestReviewedYears())
                            ->native(false)
                            ->live(),
                    ])
                    ->columns(4)
                    ->columnSpanFull()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['form_selection'] ?? null),
                                fn (Builder $query): Builder => self::applyFormTypeFilter(
                                    $query,
                                    (string) $data['form_selection'],
                                )
                            )
                            ->when(
                                filled($data['review_status'] ?? null),
                                function (Builder $query) use ($data): Builder {
                                    return match ($data['review_status']) {
                                        'passed' => $query->where('data->candidate_status', 'passed'),

                                        'pending' => $query->where(function (Builder $query): void {
                                            $query
                                                ->whereNull('data->candidate_status')
                                                ->orWhere('data->candidate_status', '')
                                                ->orWhere('data->candidate_status', 'pending');
                                        }),

                                        default => $query,
                                    };
                                }
                            )
                            ->when(
                                filled($data['reviewed_year'] ?? null),
                                function (Builder $query) use ($data): Builder {
                                    return $query->where(function (Builder $query) use ($data): void {
                                        $query->whereYear('created_at', $data['reviewed_year'])
                                            ->orWhereYear('reviewed_at', $data['reviewed_year'])
                                            ->orWhereRaw(
                                                "EXTRACT(YEAR FROM NULLIF(data->>'candidate_reviewed_at', '')::timestamp) = ?",
                                                [$data['reviewed_year']]
                                            );
                                    });
                                }
                            )
                            ->when(
                                filled($data['reviewed_month'] ?? null),
                                fn (Builder $query): Builder => $query->whereMonth('reviewed_at', $data['reviewed_month'])
                            );
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns(4)
            ->recordActions([
                Action::make('passed')
                    ->label(__('review_applications.statuses.passed'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('review_applications.passed_confirm_title'))
                    ->modalDescription(__('review_applications.passed_confirm_description'))
                    ->modalSubmitActionLabel(__('review_applications.passed_confirm_yes'))
                    ->modalCancelActionLabel(__('review_applications.passed_confirm_no'))
                    ->visible(fn (CustomFormEntry $record): bool =>
                        strtolower((string) data_get($record->data, 'candidate_status', 'pending')) === 'pending'
                    )
                    ->action(function (CustomFormEntry $record): void {
                        self::markPassed($record);

                        Notification::make()
                            ->title(NotificationLanguage::trans('review_applications.notifications.admin_passed_success_title'))
                            ->body(NotificationLanguage::trans('review_applications.notifications.admin_passed_success_body'))
                            ->success()
                            ->send();
                    }),

                Action::make('pending')
                    ->label(__('review_applications.actions.edit_result'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('review_applications.pending_modal.heading'))
                    ->modalDescription(__('review_applications.pending_modal.description'))
                    ->modalSubmitActionLabel(__('review_applications.pending_modal.submit'))
                    ->modalCancelActionLabel(__('review_applications.pending_modal.cancel'))
                    ->visible(fn (CustomFormEntry $record): bool =>
                        strtolower((string) data_get($record->data, 'candidate_status', 'pending')) === 'passed'
                        && ! self::hasStudentReviewResultNotification($record, 'passed')
                    )
                    ->action(function (CustomFormEntry $record): void {
                        self::markCandidatePending($record);

                        Notification::make()
                            ->title(NotificationLanguage::trans('review_applications.actions.edit_result'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('bulk_passed')
                    ->label(__('review_applications.statuses.passed'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading(__('review_applications.passed_confirm_title'))
                    ->modalDescription(__('review_applications.passed_confirm_description'))
                    ->modalSubmitActionLabel(__('review_applications.passed_confirm_yes'))
                    ->modalCancelActionLabel(__('review_applications.passed_confirm_no'))
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $passedCount = 0;

                        $records->each(function (CustomFormEntry $record) use (&$passedCount): void {
                            if (strtolower((string) data_get($record->data, 'candidate_status', 'pending')) !== 'pending') {
                                return;
                            }

                            self::markPassed($record);
                            $passedCount++;
                        });

                        Notification::make()
                            ->title(NotificationLanguage::trans(
                                'review_applications.notifications.bulk_passed_success_title',
                                ['count' => $passedCount]
                            ))
                            ->success()
                            ->send();
                    }),
            ]);
    }

    protected static function dynamicRequestReviewedYears(): array
    {
        return CustomFormEntry::query()
            ->get(['created_at', 'reviewed_at', 'data'])
            ->flatMap(function (CustomFormEntry $entry): array {
                $years = [];

                if ($entry->created_at) {
                    $years[] = Carbon::parse($entry->created_at)->format('Y');
                }

                if ($entry->reviewed_at) {
                    $years[] = Carbon::parse($entry->reviewed_at)->format('Y');
                }

                $candidateReviewedAt = data_get($entry->data, 'candidate_reviewed_at');

                if ($candidateReviewedAt) {
                    $years[] = Carbon::parse($candidateReviewedAt)->format('Y');
                }

                return $years;
            })
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn ($year): array => [(string) $year => (string) $year])
            ->toArray();
    }

    protected static function dynamicFormTypeOptions(): array
    {
        $options = [];

        CustomForm::query()
            ->where('menu_placement', 'sidebar')
            ->where('is_active', true)
            ->where('slug', '!=', 'profile')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function (CustomForm $form) use (&$options): void {
                $childForms = CustomForm::query()
                    ->where('custom_form_id', $form->id)
                    ->where('menu_placement', 'sub_item')
                    ->where('is_active', true)
                    ->whereNotNull('sub_item_type')
                    ->orderBy('id')
                    ->get(['id', 'name', 'custom_form_id', 'sub_item_type']);

                if (self::formHasCandidateEntries((int) $form->id, $childForms->pluck('id')->all())) {
                    $options[self::formFilterValue((int) $form->id)] = $form->display_name;
                }

                foreach ($childForms as $childForm) {
                    if (! self::subFormHasCandidateEntries($childForm)) {
                        continue;
                    }

                    $options[self::subFormFilterValue((int) $childForm->id)] = $form->display_name . ' - ' . $childForm->display_name;
                }
            });

        return $options;
    }

    protected static function applyFormTypeFilter(Builder $query, string $formType): Builder
    {
        if (str_starts_with($formType, 'form:')) {
            $formId = self::formIdFromFilterValue($formType);

            if ($formId) {
                return $query->whereIn('custom_form_id', self::sidebarFormIdsForFilter($formId));
            }
        }

        if (str_starts_with($formType, 'subform:')) {
            $subFormId = self::subFormIdFromFilterValue($formType);
            $subForm = $subFormId
                ? CustomForm::query()->whereKey($subFormId)->first(['id', 'custom_form_id', 'sub_item_type'])
                : null;

            if ($subForm) {
                return $query->where(function (Builder $query) use ($subForm): void {
                    $query->where('custom_form_id', $subForm->id);

                    if (filled($subForm->sub_item_type)) {
                        $query->orWhere(function (Builder $query) use ($subForm): void {
                            $query->where('custom_form_id', $subForm->custom_form_id)
                                ->where('data->form_selection', $subForm->sub_item_type);
                        });
                    }
                });
            }
        }

        return $query->where('data->form_selection', $formType);
    }

    protected static function formFilterValue(int $formId): string
    {
        return 'form:' . $formId;
    }

    protected static function formIdFromFilterValue(string $value): ?int
    {
        if (! str_starts_with($value, 'form:')) {
            return null;
        }

        $formId = (int) substr($value, 5);

        return $formId > 0 ? $formId : null;
    }

    protected static function subFormFilterValue(int $formId): string
    {
        return 'subform:' . $formId;
    }

    protected static function subFormIdFromFilterValue(string $value): ?int
    {
        if (! str_starts_with($value, 'subform:')) {
            return null;
        }

        $formId = (int) substr($value, 8);

        return $formId > 0 ? $formId : null;
    }

    protected static function sidebarFormIdsForFilter(int $formId): array
    {
        $childIds = CustomForm::query()
            ->where('custom_form_id', $formId)
            ->where('menu_placement', 'sub_item')
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return array_values(array_unique(array_merge([$formId], $childIds)));
    }

    protected static function candidateStatusQuery(Builder $query): Builder
    {
        return $query->whereIn('review_status', [
            'passed',
            'accepted',
            'approved',
        ]);
    }

    protected static function formHasCandidateEntries(int $formId, array $childFormIds = []): bool
    {
        $formIds = array_values(array_unique(array_merge([$formId], array_map('intval', $childFormIds))));

        return self::candidateStatusQuery(CustomFormEntry::query())
            ->whereIn('custom_form_id', $formIds)
            ->exists();
    }

    protected static function subFormHasCandidateEntries(CustomForm $subForm): bool
    {
        return self::candidateStatusQuery(CustomFormEntry::query())
            ->where(function (Builder $query) use ($subForm): void {
                $query->where('custom_form_id', $subForm->id);

                if (filled($subForm->sub_item_type)) {
                    $query->orWhere(function (Builder $query) use ($subForm): void {
                        $query->where('custom_form_id', $subForm->custom_form_id)
                            ->where('data->form_selection', $subForm->sub_item_type);
                    });
                }
            })
            ->exists();
    }

    protected static function markPassed(CustomFormEntry $record): void
    {
        $dataJson = self::normalizeData($record->data);

        $dataJson['candidate_status'] = 'passed';
        $dataJson['registration_status'] = 'passed';
        $dataJson['exam_result'] = 'passed';
        $dataJson['result_status'] = 'passed';
        $dataJson['candidate_reviewed_at'] = now()->toDateTimeString();

        DB::table('custom_form_entries')
            ->where('id', $record->id)
            ->update([
                'data' => json_encode($dataJson, JSON_UNESCAPED_UNICODE),
                'review_status' => 'passed',
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        $record->refresh();
    }

    protected static function markCandidatePending(CustomFormEntry $record): void
    {
        $dataJson = self::normalizeData($record->data);
        $dataJson['candidate_status'] = 'pending';

        DB::table('custom_form_entries')
            ->where('id', $record->id)
            ->update([
                'data' => json_encode($dataJson, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        $record->refresh();
    }

    protected static function recordFormTypeLabel(CustomFormEntry $record): string
    {
        $form = $record->customForm;

        if ($form?->menu_placement === 'sub_item') {
            $parentName = $form->parentForm?->display_name;

            return filled($parentName)
                ? $parentName . ' - ' . $form->display_name
                : $form->display_name;
        }

        $selection = (string) data_get($record->data, 'form_selection');

        if ($form?->menu_placement === 'sidebar' && filled($selection)) {
            $subForm = CustomForm::query()
                ->where('custom_form_id', $form->id)
                ->where('menu_placement', 'sub_item')
                ->where('sub_item_type', $selection)
                ->first(['name']);

            if ($subForm) {
                return $form->display_name . ' - ' . $subForm->display_name;
            }
        }

        if ($form) {
            return $form->display_name;
        }

        return self::formTypeLabel($selection);
    }

    protected static function formTypeLabel(?string $state): string
    {
        if (blank($state)) {
            return '-';
        }

        $form = CustomForm::query()
            ->where('menu_placement', 'sub_item')
            ->where('sub_item_type', $state)
            ->first(['name']);

        if ($form) {
            return $form->display_name;
        }

        return match ((string) $state) {
            'associate' => app()->getLocale() === 'km' ? 'បរិញ្ញាបត្ររង' : 'Associate',
            'bachelor' => app()->getLocale() === 'km' ? 'បរិញ្ញាបត្រ' : 'Bachelor',
            'master' => app()->getLocale() === 'km' ? 'អនុបណ្ឌិត' : 'Master',
            'phd' => app()->getLocale() === 'km' ? 'បណ្ឌិត' : 'PhD',
            default => filled($state) ? ucfirst((string) $state) : '-',
        };
    }

    protected static function statusLabel(?string $state): string
    {
        return match ($state) {
            'passed', 'accepted' => __('review_applications.statuses.passed'),
            default => __('review_applications.statuses.pending'),
        };
    }

    protected static function actionLabel(string $action): string
    {
        return match ($action) {
            'passed' => __('review_applications.statuses.passed'),
            default => __('review_applications.statuses.pending'),
        };
    }

    protected static function entryValue($record, string $key, mixed $fallback = null): string
    {
        $value = data_get($record->data, $key);

        if (blank($value)) {
            $value = $fallback;
        }

        return blank($value) ? '-' : (string) $value;
    }

    protected static function khmerName($record): string
    {
        $name = trim(collect([
            data_get($record->data, 'first_name_kh'),
            data_get($record->data, 'last_name_kh'),
        ])->filter()->join(' '));

        return filled($name) ? $name : self::entryValue($record, 'name_khmer', $record->creator?->name);
    }

    protected static function latinName($record): string
    {
        $name = trim(collect([
            data_get($record->data, 'first_name_en'),
            data_get($record->data, 'last_name_en'),
        ])->filter()->join(' '));

        return filled($name) ? strtoupper($name) : self::entryValue($record, 'name_latin', $record->creator?->name_latin);
    }

    protected static function genderLabel(string $state): string
    {
        return match (strtolower($state)) {
            'male' => app()->getLocale() === 'km' ? 'ប្រុស' : 'Male',
            'female' => app()->getLocale() === 'km' ? 'ស្រី' : 'Female',
            default => $state,
        };
    }

    protected static function dateValue(mixed $state): string
    {
        if (blank($state) || $state === '-') {
            return '-';
        }

        try {
            return Carbon::parse($state)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $state;
        }
    }

    public static function notifyStudentReviewResult(CustomFormEntry $record, string $status, ?string $note = null): bool
    {
        $student = self::getOwnerStudent($record);

        if (! $student) {
            return false;
        }

        $data = self::normalizeData($record->data);
        $studentName = self::getStudentName($data, $student->name, $student);

        if ($status === 'passed') {
            if (self::studentAlreadyHasReviewResultNotification($student, $record, $status)) {
                return false;
            }

            Notification::make()
                ->title(NotificationLanguage::transForUser(
                    $student,
                    'review_applications.notifications.student_accepted_title'
                ))
                ->body(NotificationLanguage::transForUser(
                    $student,
                    'review_applications.notifications.student_accepted_body',
                    [
                        'student' => $studentName,
                    ]
                ))
                ->icon('heroicon-o-check-circle')
                ->iconColor('success')
                ->viewData(self::reviewResultNotificationData($record, $status))
                ->success()
                ->sendToDatabase($student);

            return true;
        }

        if (self::studentAlreadyHasReviewResultNotification($student, $record, $status)) {
            return false;
        }

        Notification::make()
            ->title(NotificationLanguage::transForUser(
                $student,
                'review_applications.notifications.student_rejected_title'
            ))
            ->body(NotificationLanguage::transForUser(
                $student,
                'review_applications.notifications.student_rejected_body',
                [
                    'student' => $studentName,
                    'note' => filled($note)
                        ? $note
                        : NotificationLanguage::transForUser(
                            $student,
                            'review_applications.notifications.no_reject_note'
                        ),
                ]
            ))
            ->icon('heroicon-o-x-circle')
            ->iconColor('danger')
            ->viewData(self::reviewResultNotificationData($record, $status))
            ->danger()
            ->sendToDatabase($student);

        return true;
    }

    public static function hasStudentReviewResultNotification(CustomFormEntry $record, string $status): bool
    {
        $student = self::getOwnerStudent($record);

        if (! $student) {
            return false;
        }

        return self::studentAlreadyHasReviewResultNotification($student, $record, $status);
    }

    protected static function studentAlreadyHasReviewResultNotification(User $student, CustomFormEntry $record, string $status): bool
    {
        if (! Schema::hasTable('notifications')) {
            return false;
        }

        return DB::table('notifications')
            ->where('notifiable_type', $student->getMorphClass())
            ->where('notifiable_id', $student->getKey())
            ->where(function ($query) use ($record): void {
                $query
                    ->where('data->viewData->review_result_entry_id', (string) $record->getKey())
                    ->orWhere('data->viewData->review_result_entry_id', (int) $record->getKey());
            })
            ->where('data->viewData->review_result_status', $status)
            ->exists();
    }

    protected static function reviewResultNotificationData(CustomFormEntry $record, string $status): array
    {
        return [
            'review_result_entry_id' => (string) $record->getKey(),
            'review_result_status' => $status,
        ];
    }

    protected static function getOwnerStudent(CustomFormEntry $record): ?User
    {
        if (! Schema::hasTable('users')) {
            return null;
        }

        $studentId = null;

        foreach ([
                     'created_by',
                     'user_id',
                     'created_by_id',
                 ] as $column) {
            if (
                Schema::hasColumn('custom_form_entries', $column)
                && filled($record->{$column})
            ) {
                $studentId = $record->{$column};
                break;
            }
        }

        if (! $studentId) {
            return null;
        }

        return User::query()
            ->where('id', $studentId)
            ->where('registration_type', 'student')
            ->first();
    }

    protected static function normalizeData(mixed $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        $decoded = json_decode((string) $data, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected static function getStudentName(array $data, ?string $fallbackName = null, ?User $user = null): string
    {
        $khmerName = trim(implode(' ', array_filter([
            $data['first_name_kh'] ?? null,
            $data['last_name_kh'] ?? null,
        ])));

        if (filled($khmerName)) {
            return $khmerName;
        }

        $englishName = trim(implode(' ', array_filter([
            $data['first_name_en'] ?? null,
            $data['last_name_en'] ?? null,
        ])));

        if (filled($englishName)) {
            return $englishName;
        }

        if (filled($data['student_id'] ?? null)) {
            return (string) $data['student_id'];
        }

        return filled($fallbackName)
            ? $fallbackName
            : NotificationLanguage::transForUser($user, 'review_applications.notifications.unknown_student');
    }
}
