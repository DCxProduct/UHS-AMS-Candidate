<?php

namespace App\Filament\Livewire;

use Filament\Enums\DatabaseNotificationsPosition;
use Filament\Actions\Action;
use Filament\Livewire\DatabaseNotifications as FilamentDatabaseNotifications;
use App\Support\FilamentActionPermissions;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Tables\CustomFormEntriesTable;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

class DatabaseNotifications extends FilamentDatabaseNotifications
{
    public ?string $resubmissionNotificationId = null;

    public ?string $resubmissionEntryId = null;

    public string $resubmissionReviewNote = '';

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
        FilamentActionPermissions::abortUnlessCanForResource(CustomFormEntryResource::class, 'view_pdf');

        return CustomFormEntryResource::getEloquentQuery()->findOrFail($entryId);
    }
}
