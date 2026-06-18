<?php

namespace App\Filament\Admin\Resources\ReviewApplications\Tables;

use App\Models\User;
use App\Support\NotificationLanguage;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
                    ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
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

                TextColumn::make('reviewed_at')
                    ->label(__('review_applications.reviewed_at'))
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('review_applications.submitted_at'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('review_status')
                    ->label(__('review_applications.review_status'))
                    ->options([
                        'pending' => self::statusLabel('pending'),
                        'passed' => self::statusLabel('passed'),
                        'failed' => self::statusLabel('failed'),
                    ]),
            ])
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
                    ->label(self::actionLabel('passed'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(self::actionLabel('passed'))
                    ->modalDescription('Are you sure this application has passed?')
                    ->modalSubmitActionLabel(self::actionLabel('passed'))
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
                            ->title('Application Passed')
                            ->body('The application has been marked as passed.')
                            ->success()
                            ->send();
                    }),

                Action::make('failed')
                    ->label(self::actionLabel('failed'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalHeading(self::actionLabel('failed'))
                    ->modalSubmitActionLabel(self::actionLabel('failed'))
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
                            ->title('Application Failed')
                            ->body('The application has been marked as failed.')
                            ->success()
                            ->send();
                    }),
            ]);
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
