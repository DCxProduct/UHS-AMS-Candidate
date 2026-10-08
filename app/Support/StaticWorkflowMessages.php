<?php

namespace App\Support;

use App\Models\User;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

/**
 * The original, non-template candidate notifications.
 *
 * These messages remain the fallback for forms that have no Workflow
 * Notification assigned. They are deliberately separate from the dynamic
 * workflow sender so a configured template can never receive a static message.
 */
final class StaticWorkflowMessages
{
    public static function notifyReviewResult(
        CustomFormEntry $record,
        string $status,
        ?string $note = null,
        bool $force = false,
    ): bool {
        $student = self::owner($record);

        if ($student === null) {
            return false;
        }

        $data = self::normalizeData($record->data);
        $studentName = self::studentName($data, $student->name, $student);

        if (! $force && self::hasReviewResultNotification($record, $status)) {
            return false;
        }

        if ($force) {
            self::deleteReviewResultNotifications($student, $record, $status);
        }

        if ($status === 'passed') {
            Notification::make()
                ->title(NotificationLanguage::transForUser(
                    $student,
                    'candidate_entrance_statistics.notifications.student_accepted_title'
                ))
                ->body(NotificationLanguage::transForUser(
                    $student,
                    'candidate_entrance_statistics.notifications.student_accepted_body',
                    ['student' => $studentName]
                ))
                ->icon('heroicon-o-check-circle')
                ->iconColor('success')
                ->viewData(self::reviewResultData($record, $status))
                ->success()
                ->sendToDatabase($student);

            return true;
        }

        Notification::make()
            ->title(NotificationLanguage::transForUser(
                $student,
                'candidate_entrance_statistics.notifications.student_rejected_title'
            ))
            ->body(NotificationLanguage::transForUser(
                $student,
                'candidate_entrance_statistics.notifications.student_rejected_body',
                [
                    'student' => $studentName,
                    'note' => filled($note)
                        ? $note
                        : NotificationLanguage::transForUser(
                            $student,
                            'candidate_entrance_statistics.notifications.no_reject_note'
                        ),
                ]
            ))
            ->icon('heroicon-o-x-circle')
            ->iconColor('danger')
            ->viewData(self::reviewResultData($record, $status))
            ->danger()
            ->sendToDatabase($student);

        return true;
    }

    public static function hasReviewResultNotification(CustomFormEntry $record, string $status): bool
    {
        $student = self::owner($record);

        if ($student === null || ! Schema::hasTable('notifications')) {
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

    public static function notifyApplicationResult(
        CustomFormEntry $record,
        string $status,
        ?string $note = null,
    ): void {
        $student = self::owner($record);

        if ($student === null) {
            return;
        }

        $formName = $record->customForm?->display_name
            ?: NotificationLanguage::transForUser($student, 'app.custom_form_entry_ui.notifications.application');
        $nationalExam = (string) $record->customForm?->slug === 'national-examination-registration';

        if ($status === 'approved') {
            Notification::make()
                ->title($nationalExam
                    ? NotificationLanguage::transForUser($student, 'candidate_entrance_statistics.notifications.national_exam_approved_title')
                    : NotificationLanguage::transForUser($student, 'app.custom_form_entry_ui.notifications.application_approved_title', ['form' => $formName]))
                ->body($nationalExam
                    ? NotificationLanguage::transForUser($student, 'candidate_entrance_statistics.notifications.national_exam_approved_body')
                    : NotificationLanguage::transForUser(
                        $student,
                        ((bool) ($record->customForm?->requires_payment ?? true))
                            ? 'app.custom_form_entry_ui.notifications.application_approved_body'
                            : 'app.custom_form_entry_ui.notifications.application_approved_body_no_payment',
                        ['form' => $formName],
                    ))
                ->icon('heroicon-o-check-circle')
                ->iconColor('success')
                ->success()
                ->sendToDatabase($student);

            return;
        }

        Notification::make()
            ->title($nationalExam
                ? NotificationLanguage::transForUser($student, 'candidate_entrance_statistics.notifications.national_exam_rejected_title')
                : NotificationLanguage::transForUser($student, 'app.custom_form_entry_ui.notifications.application_rejected_title', ['form' => $formName]))
            ->body($nationalExam
                ? NotificationLanguage::transForUser($student, 'candidate_entrance_statistics.notifications.national_exam_rejected_body', [
                    'note' => filled($note)
                        ? $note
                        : NotificationLanguage::transForUser($student, 'candidate_entrance_statistics.notifications.no_reject_note'),
                ])
                : new HtmlString(NotificationLanguage::transForUser($student, 'app.custom_form_entry_ui.notifications.application_rejected_body', [
                    'form' => e($formName),
                    'note' => e(filled($note)
                        ? $note
                        : NotificationLanguage::transForUser($student, 'app.custom_form_entry_ui.notifications.no_note')),
                ])))
            ->actions($status === 'failed'
                ? []
                : array_filter([
                    self::editAction($record, NotificationLanguage::localeForUser($student)),
                ]))
            ->icon('heroicon-o-x-circle')
            ->iconColor('danger')
            ->danger()
            ->sendToDatabase($student);
    }

    public static function notifyPaymentCompleted(CustomFormEntry $record): void
    {
        $student = self::owner($record);

        if ($student === null) {
            return;
        }

        $formName = $record->customForm?->display_name
            ?: (string) ($record->customForm?->name ?? '');

        Notification::make()
            ->title(NotificationLanguage::transForUser(
                $student,
                'app.custom_form_entry_ui.notifications.payment_completed_title'
            ))
            ->body(NotificationLanguage::transForUser(
                $student,
                'app.custom_form_entry_ui.notifications.payment_completed_body',
                ['form' => $formName]
            ))
            ->icon('heroicon-o-check-circle')
            ->iconColor('success')
            ->success()
            ->sendToDatabase($student);
    }

    private static function owner(CustomFormEntry $record): ?User
    {
        if (! Schema::hasTable('users')) {
            return null;
        }

        foreach (['created_by', 'user_id', 'created_by_id'] as $column) {
            if (Schema::hasColumn('custom_form_entries', $column) && filled($record->{$column})) {
                return User::query()
                    ->whereKey($record->{$column})
                    ->where('registration_type', 'student')
                    ->first();
            }
        }

        return null;
    }

    private static function normalizeData(mixed $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        $decoded = json_decode((string) $data, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function studentName(array $data, ?string $fallback, ?User $student): string
    {
        $name = trim(implode(' ', array_filter([
            $data['first_name_kh'] ?? null,
            $data['last_name_kh'] ?? null,
        ])));

        if (filled($name)) {
            return $name;
        }

        $name = trim(implode(' ', array_filter([
            $data['first_name_en'] ?? null,
            $data['last_name_en'] ?? null,
        ])));

        if (filled($name)) {
            return $name;
        }

        return filled($data['student_id'] ?? null)
            ? (string) $data['student_id']
            : ($fallback ?: NotificationLanguage::transForUser($student, 'candidate_entrance_statistics.notifications.unknown_student'));
    }

    private static function reviewResultData(CustomFormEntry $record, string $status): array
    {
        return [
            'review_result_entry_id' => (string) $record->getKey(),
            'review_result_status' => $status,
        ];
    }

    private static function deleteReviewResultNotifications(User $student, CustomFormEntry $record, string $status): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        DB::table('notifications')
            ->where('notifiable_type', $student->getMorphClass())
            ->where('notifiable_id', $student->getKey())
            ->where(function ($query) use ($record): void {
                $query
                    ->where('data->viewData->review_result_entry_id', (string) $record->getKey())
                    ->orWhere('data->viewData->review_result_entry_id', (int) $record->getKey());
            })
            ->where('data->viewData->review_result_status', $status)
            ->delete();
    }

    private static function editAction(CustomFormEntry $record, string $locale): ?Action
    {
        if (! filled($record->getKey())) {
            return null;
        }

        return Action::make('edit_form')
            ->label(__('app.custom_form_entry_ui.actions.edit_form', [], $locale))
            ->button()
            ->color('danger')
            ->url(CustomFormEntryResource::getUrl('edit', ['record' => $record], panel: 'app'));
    }
}
