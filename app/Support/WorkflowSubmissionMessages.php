<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Localizable;
use Throwable;

final class WorkflowSubmissionMessages
{
    use Localizable;

    public static function recipientOptions(): array
    {
        $roleLabels = Role::query()->where('guard_name', 'web')->get()
            ->mapWithKeys(function (Role $role): array {
                $aliases = collect([$role->name, $role->label_en, $role->name_kh])
                    ->filter(fn (mixed $name): bool => filled($name))
                    ->mapWithKeys(fn (string $name): array => [self::recipientNameKey($name) => $role->localized_name])
                    ->all();

                return [$role->name => $aliases];
            })->all();

        return self::recipientsQuery()->with('roles')->orderBy('name')->get()
            ->mapWithKeys(fn (User $user): array => [$user->getKey() => self::recipientLabel($user, $roleLabels)])
            ->all();
    }

    private static function recipientLabel(User $user, array $roleLabels): string
    {
        $linkedUser = $user->linkedSystemUser();
        $khmerName = collect([$user->first_name_kh, $user->last_name_kh])->filter()->implode(' ')
            ?: collect([$linkedUser?->first_name_kh, $linkedUser?->last_name_kh])->filter()->implode(' ');
        $latinName = trim((string) $user->name_latin)
            ?: collect([$linkedUser?->first_name_latin, $linkedUser?->last_name_latin])->filter()->implode(' ');
        $accountName = trim((string) $user->name) ?: trim((string) $linkedUser?->name);
        $label = app()->getLocale() === 'km'
            ? ($khmerName ?: $latinName ?: $accountName)
            : ($latinName ?: $accountName ?: $khmerName);
        $label = $label ?: $user->username ?: $user->email ?: 'User';
        $key = self::recipientNameKey($label);
        $roleNames = $user->roles->pluck('name')->merge((array) ($linkedUser?->roles ?? []))->unique();

        // Translate role-named accounts while preserving personal names and IDs.
        foreach ($roleNames as $roleName) {
            if (isset($roleLabels[$roleName][$key])) {
                return $roleLabels[$roleName][$key];
            }
        }

        return $label;
    }

    private static function recipientNameKey(string $name): string
    {
        return Str::of($name)->replace(['_', '-'], ' ')->squish()->lower()->toString();
    }

    public static function statusText(CustomFormEntry $entry): ?string
    {
        $entry->loadMissing('customForm');

        // Pending applications share this label even when staff created them
        // or older submissions have no submitted_at marker. Sending stays strict.
        if ($entry->customForm?->isProfileForm()
            || strtolower(trim((string) $entry->review_status)) !== 'pending'
            || strtolower(trim((string) data_get($entry->data, 'registration_status'))) === 'draft') {
            return null;
        }

        $stage = self::configuredStage($entry);
        $status = self::text(data_get($stage, 'submission_messages.candidate_status_message'));

        return $status === '' ? null : $status;
    }

    public static function notify(CustomFormEntry $entry): int
    {
        try {
            $entry->refresh();

            if (! self::isSubmitted($entry)) {
                return 0;
            }

            $stage = self::configuredStage($entry);

            if ($stage === null) {
                return 0;
            }

            $messages = $stage['submission_messages'];
            $sent = 0;

            if (self::text($messages['candidate_notification_message'] ?? null) !== ''
                && ! WorkflowStageMessages::hasSentNotification($entry, WorkflowStageType::FormSubmission)
                && WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::FormSubmission)) {
                $sent++;
            }

            $body = self::text($messages['staff_notification_message'] ?? null);

            if ($body === '') {
                return $sent;
            }

            foreach (WorkflowStaffRecipients::resolve($messages, $entry->created_by) as $recipient) {
                if ($recipient->notifications()
                    ->where('data->viewData->workflow_entry_id', (string) $entry->getKey())
                    ->where('data->viewData->workflow_event', 'submission_staff')
                    ->exists()) {
                    continue;
                }

                (new self)->withLocale(NotificationLanguage::localeForUser($recipient), function () use ($entry, $stage, $body, $recipient): void {
                    Notification::make()
                        ->title(__('workflow_notifications.staff_notification_title', [
                            'form' => (string) ($entry->customForm?->display_name ?? ''),
                            'stage' => WorkflowNotificationStageSummary::localizedName($stage['stage_name'] ?? null, WorkflowStageType::FormSubmission),
                        ]))
                        ->body($body)
                        ->info()
                        ->icon('heroicon-o-check-circle')
                        ->iconColor('success')
                        ->viewData([
                            'workflow_entry_id' => (string) $entry->getKey(),
                            'workflow_stage_type' => WorkflowStageType::FormSubmission->value,
                            'workflow_event' => 'submission_staff',
                        ])
                        ->sendToDatabase($recipient);
                });

                $sent++;
            }

            return $sent;
        } catch (Throwable $exception) {
            report($exception);

            return 0;
        }
    }

    private static function recipientsQuery(): Builder
    {
        return User::query()->where('registration_type', 'admin')->where('is_active', true);
    }

    private static function configuredStage(CustomFormEntry $entry): ?array
    {
        if (blank($entry->custom_form_id)) {
            return null;
        }

        $workflow = WorkflowNotification::forForm($entry->custom_form_id);
        $stage = collect($workflow?->stages ?? [])
            ->map(fn (mixed $item): array => is_array($item) ? ($item['data'] ?? $item) : [])
            ->first(fn (array $data): bool => ($data['stage_type'] ?? null) === WorkflowStageType::FormSubmission->value);

        return is_array($stage['submission_messages'] ?? null) ? $stage : null;
    }

    private static function isSubmitted(CustomFormEntry $entry): bool
    {
        $entry->loadMissing('customForm');

        return ! $entry->customForm?->isProfileForm()
            && filled(data_get($entry->data, 'submitted_at'))
            && strtolower(trim((string) $entry->review_status)) === 'pending'
            && strtolower(trim((string) data_get($entry->data, 'registration_status'))) !== 'draft'
            && User::query()->whereKey($entry->created_by)->where('registration_type', 'student')->exists();
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
