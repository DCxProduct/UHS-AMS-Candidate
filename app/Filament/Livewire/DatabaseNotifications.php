<?php

namespace App\Filament\Livewire;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Tables\CustomFormEntriesTable;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Enums\DatabaseNotificationsPosition;
use Filament\Livewire\DatabaseNotifications as FilamentDatabaseNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class DatabaseNotifications extends FilamentDatabaseNotifications
{
    public ?string $resubmissionNotificationId = null;

    public ?string $resubmissionEntryId = null;

    public string $resubmissionReviewNote = '';

    #[Locked]
    public ?string $candidateCorrectionNotificationId = null;

    public string $candidateCorrectionMessage = '';

    public function candidateCorrectionEntryId(array $data): ?string
    {
        if (filament()->auth()->user()?->registration_type !== 'student') {
            return null;
        }

        $entryId = data_get($data, 'viewData.candidate_correction_entry_id');
        // Notifications sent before correction metadata was added remain usable.
        if (blank($entryId) && data_get($data, 'viewData.workflow_event') === 'review_action:send_back') {
            $entryId = data_get($data, 'viewData.workflow_entry_id');
        }

        return is_scalar($entryId) && preg_match('/^[1-9][0-9]*$/', (string) $entryId)
            ? (string) $entryId : null;
    }

    public function openCandidateCorrection(string $notificationId): void
    {
        [$notification] = $this->resolveCandidateCorrection($notificationId);
        $this->candidateCorrectionNotificationId = (string) $notification->getKey();
        $this->candidateCorrectionMessage = trim((string) data_get($notification->data, 'body', ''));

        $this->dispatch('close-modal', id: 'database-notifications');
        $this->dispatch('open-modal', id: 'candidate-correction-note-modal');
    }

    public function confirmCandidateCorrection(): void
    {
        abort_if(blank($this->candidateCorrectionNotificationId), 404);
        // Check ownership, form access and current review status again on confirm.
        [$notification, $entry] = $this->resolveCandidateCorrection($this->candidateCorrectionNotificationId);
        $notification->markAsRead();
        $this->dispatch('close-modal', id: 'candidate-correction-note-modal');
        $this->redirect(CustomFormEntryResource::getUrl('edit', ['record' => $entry], panel: 'app'));
    }

    protected function resolveCandidateCorrection(string $notificationId): array
    {
        abort_unless(filament()->auth()->user()?->registration_type === 'student', 403);
        $notification = $this->getNotificationsQuery()->whereKey($notificationId)->firstOrFail();
        $entryId = $this->candidateCorrectionEntryId($notification->data);
        abort_if($entryId === null, 404);

        $entry = CustomFormEntryResource::getEloquentQuery()->with('customForm')->findOrFail($entryId);
        Gate::authorize('update', $entry);
        abort_unless($entry->customForm && CustomFormEntryResource::canCurrentUserAccessForm($entry->customForm), 403);
        $status = strtolower(trim((string) $entry->review_status));
        $registrationStatus = strtolower(trim((string) data_get($entry->data, 'registration_status')));
        abort_unless($status === 'rejected' && ! in_array($registrationStatus, ['failed', 'approved', 'accepted', 'passed', 'completed'], true), 403);

        return [$notification, $entry];
    }

    public function clearNotificationsAction(): Action
    {
        return parent::clearNotificationsAction()->hidden();
    }

    public function getTrigger(): View
    {
        return view('filament.components.database-notifications-trigger', [
            'isTopbar' => ($this->position ?? filament()->getDatabaseNotificationsPosition()) === DatabaseNotificationsPosition::Topbar,
            'unreadNotificationsCount' => $this->getUnreadNotificationsCount(),
        ]);
    }

    public function openResubmissionReview(string $notificationId): void
    {
        $notification = $this->getNotificationsQuery()
            ->whereKey($notificationId)
            ->firstOrFail();
        $entryId = data_get($notification->data, 'viewData.resubmission_entry_id');

        if (blank($entryId)) {
            abort(404);
        }

        $this->resolveReviewEntry((string) $entryId);

        $this->resubmissionNotificationId = (string) $notification->getKey();
        $this->resubmissionEntryId = (string) $entryId;
        $this->resubmissionReviewNote = trim((string) data_get($notification->data, 'viewData.registrar_review_note', ''));

        $this->dispatch('close-modal', id: 'database-notifications');
        $this->dispatch('open-modal', id: 'resubmission-review-note-modal');
    }

    #[On('open-resubmission-review-for-entry')]
    public function openResubmissionReviewForEntry(string $entryId): void
    {
        $entry = $this->resolveReviewEntry($entryId);

        $this->resubmissionNotificationId = null;
        $this->resubmissionEntryId = (string) $entry->getKey();
        $this->resubmissionReviewNote = trim((string) ($entry->review_note ?? ''));

        $this->dispatch('close-modal', id: 'database-notifications');

        if (blank($this->resubmissionReviewNote)) {
            $this->openEntryReviewModal((string) $entry->getKey());

            return;
        }

        $this->dispatch('open-modal', id: 'resubmission-review-note-modal');
    }

    public function confirmResubmissionReview(): void
    {
        if (filled($this->resubmissionNotificationId)) {
            $notification = $this->getNotificationsQuery()
                ->whereKey($this->resubmissionNotificationId)
                ->firstOrFail();
            $entryId = data_get($notification->data, 'viewData.resubmission_entry_id');

            if (blank($entryId) || (string) $entryId !== $this->resubmissionEntryId) {
                abort(404);
            }
        } else {
            $entryId = $this->resubmissionEntryId;
        }

        abort_if(blank($entryId), 404);

        $this->openEntryReviewModal((string) $entryId);

        if (isset($notification)) {
            $this->getNotificationsQuery()
                ->whereKey($notification->getKey())
                ->update(['read_at' => now()]);
        }
    }

    #[On('open-entry-review-modal')]
    public function openEntryReviewModal(string $entryId): void
    {
        $entry = $this->resolveReviewEntry($entryId);

        $this->dispatch('close-modal', id: 'resubmission-review-note-modal');
        $this->dispatch('close-modal', id: 'database-notifications');

        $this->cacheAction($this->applicationReviewAction());
        $this->replaceMountedAction('applicationReview', ['entryId' => (string) $entry->getKey()]);
    }

    public function applicationReviewAction(): Action
    {
        return CustomFormEntriesTable::reviewApplicationAction('applicationReview')
            ->record(fn (array $arguments): CustomFormEntry => $this->resolveReviewEntry((string) ($arguments['entryId'] ?? '')));
    }

    protected function resolveReviewEntry(string $entryId): CustomFormEntry
    {
        abort_unless(CustomFormEntryResource::currentUserCanManageForms(), 403);
        $entry = CustomFormEntryResource::getEloquentQuery()->findOrFail($entryId);
        Gate::authorize('view', $entry);
        abort_unless(CustomFormEntriesTable::canViewReviewPdf($entry), 403);

        return $entry;
    }
}
