<?php

namespace App\Filament\Livewire;

use Filament\Enums\DatabaseNotificationsPosition;
use Filament\Actions\Action;
use Filament\Livewire\DatabaseNotifications as FilamentDatabaseNotifications;
use App\Support\FilamentActionPermissions;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
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

        $this->resubmissionNotificationId = (string) $notification->getKey();
        $this->resubmissionEntryId = (string) $entryId;
        $this->resubmissionReviewNote = trim((string) data_get($notification->data, 'viewData.registrar_review_note', ''));

        $this->dispatch('close-modal', id: 'database-notifications');
        $this->dispatch('open-modal', id: 'resubmission-review-note-modal');
    }

    #[On('open-resubmission-review-for-entry')]
    public function openResubmissionReviewForEntry(string $entryId): void
    {
        abort_unless(CustomFormEntryResource::currentUserCanManageForms(), 403);
        FilamentActionPermissions::abortUnlessCanForResource(CustomFormEntryResource::class, 'view_pdf');

        $entry = CustomFormEntry::query()->findOrFail($entryId);

        $this->resubmissionNotificationId = null;
        $this->resubmissionEntryId = (string) $entry->getKey();
        $this->resubmissionReviewNote = trim((string) ($entry->review_note ?? ''));

        $this->dispatch('close-modal', id: 'database-notifications');

        if (blank($this->resubmissionReviewNote)) {
            $this->dispatch('open-entry-review-modal', entryId: (string) $entry->getKey());

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

            $this->getNotificationsQuery()
                ->whereKey($notification->getKey())
                ->update(['read_at' => now()]);
        } else {
            abort_unless(CustomFormEntryResource::currentUserCanManageForms(), 403);
            FilamentActionPermissions::abortUnlessCanForResource(CustomFormEntryResource::class, 'view_pdf');
            $entryId = $this->resubmissionEntryId;
        }

        abort_if(blank($entryId), 404);

        $entry = CustomFormEntry::query()->findOrFail($entryId);

        $this->dispatch('close-modal', id: 'resubmission-review-note-modal');
        $this->dispatch('close-modal', id: 'database-notifications');
        $this->dispatch('open-entry-review-modal', entryId: (string) $entry->getKey());
    }
}
