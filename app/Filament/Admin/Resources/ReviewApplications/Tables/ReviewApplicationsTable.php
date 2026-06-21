<?php

namespace App\Filament\Admin\Resources\ReviewApplications\Tables;

use App\Models\User;
use App\Support\NotificationLanguage;
use Carbon\Carbon;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Select;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Support\HtmlString;

class ReviewApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('review_applications.id'))
                    ->sortable(),

                TextColumn::make('creator.name')
                    ->label(__('review_applications.student'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('data.form_selection')
                    ->label(__('review_applications.form_type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::formTypeLabel($state))
                    ->color('info')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('data.student_id')
                    ->label(__('review_applications.student_id'))
                    ->searchable(),

                TextColumn::make('data.first_name_en')
                    ->label(__('review_applications.first_name_en'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('data.last_name_en')
                    ->label(__('review_applications.last_name_en'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('data.first_name_kh')
                    ->label(__('review_applications.first_name_kh'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('data.last_name_kh')
                    ->label(__('review_applications.last_name_kh'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('review_status')
                    ->label(__('review_applications.review_status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? __('review_applications.statuses.' . $state) : '-')
                    ->color(fn (?string $state): string => match ($state) {
                        'passed', 'accepted' => 'success',
                        'failed', 'rejected' => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('review_note')
                    ->label(__('review_applications.review_note'))
                    ->limit(40)
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('review_applications.submitted_at'))
                    ->dateTime('d M Y H:i')
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('reviewed_at')
                    ->label(__('review_applications.reviewed_at'))
                    ->dateTime('d M Y H:i')
                    ->color('info')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('application_review_filters')
                    ->label(new HtmlString('&nbsp;'))
                    ->schema([
                        Select::make('form_selection')
                            ->label(__('review_applications.form_type'))
                            ->options(function (): array {
                                return CustomFormEntry::query()
                                    ->whereNotNull('data->form_selection')
                                    ->get(['data'])
                                    ->pluck('data.form_selection')
                                    ->filter()
                                    ->unique()
                                    ->mapWithKeys(fn ($item) => [
                                        (string) $item => ucfirst((string) $item)
                                    ])
                                    ->toArray();
                            })
                            ->native(false)
                            ->live(),

                        Select::make('review_status')
                            ->label(__('review_applications.review_status'))
                            ->options([
                                'pending' => self::statusLabel('pending'),
                                'passed' => self::statusLabel('passed'),
                                'failed' => self::statusLabel('failed'),
                            ])
                            ->native(false)
                            ->live(),

                        Select::make('reviewed_month')
                            ->label(__('review_applications.reviewed_month'))
                            ->options(function (): array {
                                return collect(range(1, 12))
                                    ->mapWithKeys(fn ($month) => [
                                        (string) $month => __('review_applications.months.' . $month)
                                    ])
                                    ->toArray();
                            })
                            ->native(false)
                            ->live(),

                        Select::make('reviewed_year')
                            ->label(__('review_applications.reviewed_year'))
                            ->options(
                                collect(range(2025, 2050))
                                    ->mapWithKeys(fn ($year) => [(string) $year => (string) $year])
                                    ->toArray()
                            )
                            ->native(false)
                            ->live(),
                    ])
                    ->columns(4)
                    ->columnSpanFull()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['form_selection'] ?? null),
                                fn (Builder $query): Builder => $query->where('data->form_selection', $data['form_selection'])
                            )
                            ->when(
                                filled($data['review_status'] ?? null),
                                fn (Builder $query): Builder => $query->where('review_status', $data['review_status'])
                            )
                            ->when(
                                filled($data['reviewed_year'] ?? null),
                                fn (Builder $query): Builder => $query->whereYear('reviewed_at', $data['reviewed_year'])
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
                Action::make('view_details')
                    ->label(__('review_applications.actions.view_details'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalWidth('6xl')
                    ->modalHeading(__('review_applications.details_title'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('review_applications.actions.close'))
                    ->modalContent(fn (CustomFormEntry $record) => view(
                        'filament.admin.resources.review-applications.view-entry',
                        [
                            'record' => $record,
                            'data' => self::normalizeData($record->data),
                        ],
                    )),

                Action::make('passed')
                    ->label(__('review_applications.statuses.passed'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('review_applications.actions.passed'))
                    ->modalDescription(__('review_applications.passed_confirm_description'))
                    ->modalSubmitActionLabel(__('review_applications.actions.passed'))
                    ->visible(fn (CustomFormEntry $record): bool => ! in_array($record->review_status, ['passed', 'accepted'], true))
                    ->action(function (CustomFormEntry $record): void {
                        DB::table('custom_form_entries')
                            ->where('id', $record->id)
                            ->update([
                                'review_status' => 'passed',
                                'review_note' => null,
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                                'updated_at' => now(),
                            ]);

                        $record->refresh();

                        self::notifyStudentReviewResult(
                            record: $record,
                            status: 'passed',
                            note: null,
                        );

                        Notification::make()
                            ->title(__('review_applications.notifications.admin_passed_success_title'))
                            ->body(__('review_applications.notifications.admin_passed_success_body'))
                            ->success()
                            ->send();
                    }),

                Action::make('failed')
                    ->label(__('review_applications.statuses.failed'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalHeading(__('review_applications.actions.failed'))
                    ->modalSubmitActionLabel(__('review_applications.actions.failed'))
                    ->visible(fn (CustomFormEntry $record): bool => ! in_array($record->review_status, ['failed', 'rejected'], true))
                    ->form([
                        Textarea::make('review_note')
                            ->label(__('review_applications.review_note'))
                            ->placeholder(__('review_applications.review_note_placeholder'))
                            ->required()
                            ->rows(4),
                    ])
                    ->action(function (CustomFormEntry $record, array $data): void {
                        $note = $data['review_note'] ?? null;

                        DB::table('custom_form_entries')
                            ->where('id', $record->id)
                            ->update([
                                'review_status' => 'failed',
                                'review_note' => $note,
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                                'updated_at' => now(),
                            ]);

                        $record->refresh();

                        self::notifyStudentReviewResult(
                            record: $record,
                            status: 'failed',
                            note: $note,
                        );

                        Notification::make()
                            ->title(__('review_applications.notifications.admin_failed_success_title'))
                            ->body(__('review_applications.notifications.admin_failed_success_body'))
                            ->success()
                            ->send();
                    }),
            ]);
    }

    protected static function formTypeLabel(?string $state): string
    {
        return match ($state) {
            'master' => 'Master',
            default => filled($state) ? ucfirst((string) $state) : '-',
        };
    }

    protected static function statusLabel(?string $state): string
    {
        return match ($state) {
            'passed', 'accepted' => 'Passed',
            'failed', 'rejected' => 'Failed',
            default => 'Pending',
        };
    }

    protected static function actionLabel(string $action): string
    {
        return match ($action) {
            'passed' => 'Passed',
            'failed' => 'Failed',
            default => 'Pending',
        };
    }

    protected static function notifyStudentReviewResult(CustomFormEntry $record, string $status, ?string $note = null): void
    {
        $student = self::getOwnerStudent($record);

        if (! $student) {
            return;
        }

        $data = self::normalizeData($record->data);
        $studentName = self::getStudentName($data, $student->name);

        if (in_array($status, ['passed', 'accepted'], true)) {
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
                ->success()
                ->sendToDatabase($student);

            return;
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
            ->danger()
            ->sendToDatabase($student);
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

    protected static function getStudentName(array $data, ?string $fallbackName = null): string
    {
        $khmerName = trim(implode(' ', array_filter([
            $data['last_name_kh'] ?? null,
            $data['first_name_kh'] ?? null,
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
            : __('review_applications.notifications.unknown_student');
    }
}
