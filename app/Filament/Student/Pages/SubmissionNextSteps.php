<?php

namespace App\Filament\Student\Pages;

use App\Support\SubmissionNextSteps as NextSteps;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

class SubmissionNextSteps extends Page
{
    protected string $view = 'filament.student.pages.submission-next-steps';

    protected static ?string $slug = 'submission-next-steps/{record}';

    protected static bool $shouldRegisterNavigation = false;

    #[Locked]
    public int $entryId;

    public static function canAccess(): bool
    {
        return auth()->user()?->registration_type === 'student';
    }

    public function mount(int|string $record): void
    {
        abort_unless(self::canAccess(), 403);
        $this->entryId = (int) $record;
        $entry = $this->getEntry();

        if (! NextSteps::contentForEntry($entry)) {
            $this->redirect(NextSteps::continueUrl($entry));
        }
    }

    public function getEntry(): CustomFormEntry
    {
        abort_unless(self::canAccess(), 403);
        $entry = CustomFormEntryResource::getEloquentQuery()->with('customForm')->findOrFail($this->entryId);
        Gate::authorize('view', $entry);
        abort_unless($entry->customForm && CustomFormEntryResource::canCurrentUserAccessForm($entry->customForm), 403);
        abort_unless(NextSteps::isSubmitted($entry), 404);

        return $entry;
    }

    public function getTitle(): string
    {
        if (isset($this->entryId)) {
            return NextSteps::contentForEntry($this->getEntry())['heading'] ?? __('submission_next_steps.submitted');
        }

        return __('submission_next_steps.submitted');
    }

    protected function getViewData(): array
    {
        $entry = $this->getEntry();

        return [
            'nextSteps' => NextSteps::contentForEntry($entry),
            'continueUrl' => NextSteps::continueUrl($entry),
        ];
    }
}
