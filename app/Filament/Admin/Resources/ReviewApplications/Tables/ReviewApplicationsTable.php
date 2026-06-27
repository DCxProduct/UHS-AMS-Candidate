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

                TextColumn::make('review_note')
                    ->label(__('review_applications.review_note'))
                    ->limit(40)
                    ->toggleable(),

                TextColumn::make('reviewed_at')
                    ->label(__('review_applications.approve_at'))
                    ->dateTime('d M Y H:i')
                    ->color('gray'),

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
                            ])
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
                    ->columns(3)
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
                    ->visible(fn (CustomFormEntry $record): bool =>
                        strtolower((string) data_get($record->data, 'candidate_status', 'pending')) === 'pending'
                    )
                    ->action(function (CustomFormEntry $record): void {
                        $dataJson = self::normalizeData($record->data);
                        $dataJson['candidate_status'] = 'passed';
                        $dataJson['candidate_reviewed_at'] = now()->toDateTimeString();

                        DB::table('custom_form_entries')
                            ->where('id', $record->id)
                            ->update([
                                'data' => json_encode($dataJson),
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
