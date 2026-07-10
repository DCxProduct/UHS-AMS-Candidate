<?php

namespace App\Filament\Admin\Resources\ReviewApplications\Tables;

use App\Models\User;
use App\Support\NotificationLanguage;
use Carbon\Carbon;
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
                            ->options(function (): array {
                                return CustomFormEntry::query()
                                    ->whereNotNull('data->form_selection')
                                    ->get(['data'])
                                    ->pluck('data.form_selection')
                                    ->filter()
                                    ->unique()
                                    ->mapWithKeys(fn ($item) => [
                                        (string) $item => ucfirst((string) $item),
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
                                fn (Builder $query): Builder => $query->where('data->form_selection', $data['form_selection'])
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
                    ->modalHeading(__('review_applications.actions.passed'))
                    ->modalDescription(__('review_applications.passed_confirm_description'))
                    ->modalSubmitActionLabel(__('review_applications.actions.passed'))
                    ->visible(fn (CustomFormEntry $record): bool =>
                        strtolower((string) data_get($record->data, 'candidate_status', 'pending')) === 'pending'
                    )
                    ->action(function (CustomFormEntry $record): void {
                        self::markPassed($record);

                        Notification::make()
                            ->title(__('review_applications.notifications.admin_passed_success_title'))
                            ->body(__('review_applications.notifications.admin_passed_success_body'))
                            ->success()
                            ->send();
                    }),

                Action::make('pending')
                    ->label(__('review_applications.statuses.pending'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('review_applications.statuses.pending'))
                    ->modalSubmitActionLabel(__('review_applications.statuses.pending'))
                    ->visible(fn (CustomFormEntry $record): bool =>
                        strtolower((string) data_get($record->data, 'candidate_status', 'pending')) === 'passed'
                    )
                    ->action(function (CustomFormEntry $record): void {
                        self::markCandidatePending($record);

                        Notification::make()
                            ->title(__('review_applications.statuses.pending'))
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
                    ->modalHeading(__('review_applications.actions.passed'))
                    ->modalDescription(__('review_applications.passed_confirm_description'))
                    ->modalSubmitActionLabel(__('review_applications.actions.passed'))
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
                            ->title($passedCount . ' candidates passed')
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

        self::notifyStudentReviewResult(
            record: $record,
            status: 'passed',
            note: null,
        );
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
            default => 'Pending',
        };
    }

    protected static function actionLabel(string $action): string
    {
        return match ($action) {
            'passed' => 'Passed',
            default => 'Pending',
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
            data_get($record->data, 'last_name_kh'),
            data_get($record->data, 'first_name_kh'),
        ])->filter()->join(' '));

        return filled($name) ? $name : self::entryValue($record, 'name_khmer', $record->creator?->name);
    }

    protected static function latinName($record): string
    {
        $name = trim(collect([
            data_get($record->data, 'last_name_en'),
            data_get($record->data, 'first_name_en'),
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

    protected static function notifyStudentReviewResult(CustomFormEntry $record, string $status, ?string $note = null): void
    {
        $student = self::getOwnerStudent($record);

        if (! $student) {
            return;
        }

        $data = self::normalizeData($record->data);
        $studentName = self::getStudentName($data, $student->name);

        if ($status === 'passed') {
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
