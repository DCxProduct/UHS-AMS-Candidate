<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;

use App\Models\ClosingDate;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Tables\CustomFormEntriesTable;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Schema;

class ListCustomFormEntries extends ListRecords
{
    protected static string $resource = CustomFormEntryResource::class;

    public ?string $activeFormId = null;

    public function mount(): void
    {
        parent::mount();

        $this->activeFormId = request()->input('tableFilters.custom_form_id.value')
            ?? data_get(request()->query('tableFilters'), 'custom_form_id.value')
            ?? request()->query('form_id');

        if (! $this->activeFormId) {
            return;
        }

        $customForm = CustomForm::find($this->activeFormId);

        if (! $customForm) {
            return;
        }

        if ($this->currentUserIsAdmin()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Closed Form
        |--------------------------------------------------------------------------
        | Keep the sidebar menu visible and redirect the student to Contact Us.
        |--------------------------------------------------------------------------
        */
        if (ClosingDate::shouldShowContact($customForm->id)) {
            $this->redirect(
                url('/contact-us?form_id=' . $customForm->id)
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Not Open Form
        |--------------------------------------------------------------------------
        | Keep the old workflow: block direct access.
        |--------------------------------------------------------------------------
        */
        if (
            $customForm->slug !== 'profile'
            && ! ClosingDate::isCustomFormOpen($customForm->id)
        ) {
            abort(403, 'This application is not open.');
        }

        /*
        |--------------------------------------------------------------------------
        | Existing Profile Workflow
        |--------------------------------------------------------------------------
        | Keep the old profile create/edit/draft behavior unchanged.
        |--------------------------------------------------------------------------
        */
        if ($customForm->slug === 'profile' && $this->currentUserUsesCandidateFlow()) {
            $entry = $this->studentCurrentFormEntry();

            if ($entry) {
                if ($this->isDraftEntry($entry)) {
                    $this->redirect(
                        CustomFormEntryResource::getUrl('create', [
                            'form_id' => $this->activeFormId,
                        ])
                    );

                    return;
                }

                $this->redirect(
                    CustomFormEntryResource::getUrl('edit', [
                        'record' => $entry->id,
                    ])
                );

                return;
            }

            $this->redirect(
                CustomFormEntryResource::getUrl('create', [
                    'form_id' => $this->activeFormId,
                ])
            );

            return;
        }
    }

    protected function currentUserIsAdmin(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return method_exists($user, 'hasEffectiveRole')
            ? $user->hasEffectiveRole('admin')
            : $user->registration_type === 'admin';
    }

    protected function studentCurrentFormEntry(): ?CustomFormEntry
    {
        if (! $this->activeFormId || ! auth()->check()) {
            return null;
        }

        $userId = auth()->id();

        return CustomFormEntry::query()
            ->where('custom_form_id', $this->activeFormId)
            ->where(function ($query) use ($userId): void {
                if (Schema::hasColumn('custom_form_entries', 'created_by')) {
                    $query->orWhere('created_by', $userId);
                }

                if (Schema::hasColumn('custom_form_entries', 'user_id')) {
                    $query->orWhere('user_id', $userId);
                }

                if (Schema::hasColumn('custom_form_entries', 'created_by_id')) {
                    $query->orWhere('created_by_id', $userId);
                }
            })
            ->latest('id')
            ->first();
    }

    public function updatedTableFilters(): void
    {
        $filterFormId = data_get(
            $this->tableFilters,
            'custom_form_id.value'
        );

        if (filled($filterFormId)) {
            $this->activeFormId = $filterFormId;

            return;
        }

        $this->activeFormId = request()->input(
            'tableFilters.custom_form_id.value'
        )
            ?? data_get(
            request()->query('tableFilters'),
            'custom_form_id.value'
        )
            ?? request()->query('form_id')
            ?? $this->activeFormId;
    }

    public function getHeading(): string|Htmlable
    {
        if ($this->activeFormId) {
            $customForm = CustomForm::find($this->activeFormId);

            if ($customForm) {
                $slug = strtolower(
                    trim((string) ($customForm->slug ?? ''))
                );

                return match ($slug) {
                    'profile' => __('navigation.forms.profile'),

                    'national-examination-registration' =>
                    __('navigation.national_examination_registration'),

                    default =>
                    $customForm->display_name
                        ?: __('navigation.forms.untitled'),
                };
            }
        }

        return __('filament-custom-forms::fcf.entry.plural');
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getHeading();
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\CreateAction::make()
                ->label($this->getCreateLabel())
                ->url(
                    fn () => CustomFormEntryResource::getUrl(
                        'create',
                        [
                            'form_id' => $this->activeFormId,
                        ]
                    )
                )
                ->visible(
                    fn (): bool => $this->currentUserCanCreateCurrentForm()
                ),
        ];

        if ($this->currentUserIsAdmin()) {
            $actions[] = Action::make('download_excel')
                ->label(__('payments.actions.download_excel'))
                ->color('success')
                ->alpineClickHandler(<<<'JS'
                    const table = document.querySelector('.fi-ta');
                    const tableData = table?._x_dataStack?.find((data) => data.selectedRecords instanceof Set);

                    $wire.downloadExcelFromTableSelection(
                        tableData ? [...tableData.selectedRecords] : [],
                        tableData ? tableData.isTrackingDeselectedRecords : false,
                        tableData ? [...tableData.deselectedRecords] : [],
                    );
                JS)
                ->action(fn () => $this->downloadExcel());

            $actions[] = Action::make('clear_data')
                ->label(__('app.clear_data'))
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('app.clear_data'))
                ->modalDescription(__('app.clear_data_confirm'))
                ->modalSubmitActionLabel(__('app.delete'))
                ->modalCancelActionLabel(__('app.cancel'))
                ->alpineClickHandler(<<<'JS'
                    const table = document.querySelector('.fi-ta');
                    const tableData = table?._x_dataStack?.find((data) => data.selectedRecords instanceof Set);

                    $wire.mountAction('clear_data', {
                        selectedRecordKeys: tableData ? [...tableData.selectedRecords] : [],
                        isTrackingDeselectedRecords: tableData ? tableData.isTrackingDeselectedRecords : false,
                        deselectedRecordKeys: tableData ? [...tableData.deselectedRecords] : [],
                    });
                JS)
                ->action(fn (array $arguments) => $this->clearDataFromTableSelection(
                    $arguments['selectedRecordKeys'] ?? [],
                    (bool) ($arguments['isTrackingDeselectedRecords'] ?? false),
                    $arguments['deselectedRecordKeys'] ?? [],
                ));
        }

        return $actions;
    }

    protected function downloadExcel()
    {
        return $this->downloadExcelFromTableSelection(
            $this->selectedTableRecords ?? [],
            $this->isTrackingDeselectedTableRecords,
            $this->deselectedTableRecords ?? [],
        );
    }

    public function downloadExcelFromTableSelection(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    )
    {
        return CustomFormEntriesTable::downloadExcel(
            $this->selectedOrFilteredQuery(
                $selectedRecordKeys,
                $isTrackingDeselectedRecords,
                $deselectedRecordKeys,
            )->get(),
            $this->visibleExportColumnKeys(),
            $this->activeFormId,
            $this->getTable(),
        );
    }

    public function clearDataFromTableSelection(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    ): void {
        $this->selectedOrFilteredQuery(
            $selectedRecordKeys,
            $isTrackingDeselectedRecords,
            $deselectedRecordKeys,
        )->delete();

        $this->selectedTableRecords = [];
        $this->deselectedTableRecords = [];
        $this->isTrackingDeselectedTableRecords = false;
        $this->flushCachedTableRecords();
        $this->dispatch('$refresh');
    }

    protected function selectedOrFilteredQuery(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    ) {
        $selectedRecordKeys = array_values(array_filter($selectedRecordKeys));
        $deselectedRecordKeys = array_values(array_filter($deselectedRecordKeys));

        $query = $this->getFilteredTableQuery();

        if ($isTrackingDeselectedRecords) {
            if (filled($deselectedRecordKeys)) {
                $query->whereKeyNot($deselectedRecordKeys);
            }

            return $query;
        }

        if (filled($selectedRecordKeys)) {
            $query->whereKey($selectedRecordKeys);
        }

        return $query;
    }

    protected function visibleExportColumnKeys(): array
    {
        $keys = [];

        foreach ($this->tableColumns ?? [] as $item) {
            if (($item['type'] ?? null) === 'column' && ($item['isToggled'] ?? false)) {
                $keys[] = (string) $item['name'];

                continue;
            }

            if (($item['type'] ?? null) !== 'group') {
                continue;
            }

            foreach ($item['columns'] ?? [] as $column) {
                if ($column['isToggled'] ?? false) {
                    $keys[] = (string) $column['name'];
                }
            }
        }

        return $keys;
    }

    protected function currentUserCanCreateCurrentForm(): bool
    {
        $user = auth()->user();

        if (! $user || ! $this->activeFormId) {
            return false;
        }

        if (! $user->can('Create:CustomFormEntry')) {
            return false;
        }

        $customForm = CustomForm::find($this->activeFormId);

        if (! $customForm || ! CustomFormEntryResource::canCurrentUserAccessForm($customForm)) {
            return false;
        }

        if (
            ! $this->currentUserUsesCandidateFlow()
        ) {
            return true;
        }

        return ! $this->studentHasAnyCurrentFormEntry();
    }

    protected function currentUserUsesCandidateFlow(): bool
    {
        $user = auth()->user();

        if (! $user || $user->registration_type !== 'student') {
            return false;
        }

        if (
            method_exists($user, 'hasEffectiveRole')
            && $user->hasEffectiveRole([
                'admin',
                'cashier',
                'finance',
                'developer',
                'registrar',
                'processing',
                'team uhs',
            ])
        ) {
            return false;
        }

        return true;
    }

    protected function getCreateLabel(): string
    {
        $name = __('filament-custom-forms::fcf.entry.single');

        if ($this->activeFormId) {
            $customForm = CustomForm::find($this->activeFormId);

            if ($customForm) {
                $slug = strtolower(
                    trim((string) ($customForm->slug ?? ''))
                );

                $translated = match ($slug) {
                    'profile' => __('navigation.forms.profile'),

                    'national-examination-registration' =>
                    __('navigation.national_examination_registration'),

                    default => $customForm->display_name,
                };

                $name = $translated;
            }
        }

        return __('filament-custom-forms::fcf.entry.action.create', [
            'name' => $name,
        ]);
    }

    protected function studentAlreadySubmittedCurrentForm(): bool
    {
        if (! $this->activeFormId || ! auth()->check()) {
            return false;
        }

        if (! Schema::hasTable('custom_form_entries')) {
            return false;
        }

        $userId = auth()->id();

        return CustomFormEntry::query()
            ->where('custom_form_id', $this->activeFormId)
            ->where(function ($query) use ($userId): void {
                if (Schema::hasColumn('custom_form_entries', 'created_by')) {
                    $query->orWhere('created_by', $userId);
                }

                if (Schema::hasColumn('custom_form_entries', 'user_id')) {
                    $query->orWhere('user_id', $userId);
                }

                if (Schema::hasColumn('custom_form_entries', 'created_by_id')) {
                    $query->orWhere('created_by_id', $userId);
                }
            })
            ->get()
            ->contains(
                fn (CustomFormEntry $entry): bool =>
                ! $this->isDraftEntry($entry)
            );
    }

    protected function studentHasAnyCurrentFormEntry(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        if (! Schema::hasTable('custom_form_entries')) {
            return false;
        }

        $formId = $this->activeFormId;

        if (! $formId) {
            return false;
        }

        $userId = auth()->id();

        return CustomFormEntry::query()
            ->where('custom_form_id', $formId)
            ->where(function ($query) use ($userId): void {
                if (Schema::hasColumn('custom_form_entries', 'created_by')) {
                    $query->orWhere('created_by', $userId);
                }

                if (Schema::hasColumn('custom_form_entries', 'user_id')) {
                    $query->orWhere('user_id', $userId);
                }

                if (Schema::hasColumn('custom_form_entries', 'created_by_id')) {
                    $query->orWhere('created_by_id', $userId);
                }
            })
            ->whereIn('review_status', [
                'draft',
                'pending',
                'approved',
                'accepted',
                'passed',
                'rejected',
                'failed',
            ])
            ->exists();
    }

    protected function isDraftEntry(CustomFormEntry $entry): bool
    {
        $data = is_array($entry->data)
            ? $entry->data
            : json_decode((string) $entry->data, true);

        $dataStatus = strtolower(
            (string) data_get($data, 'registration_status')
        );

        $reviewStatus = strtolower(
            (string) ($entry->review_status ?? '')
        );

        return $dataStatus === 'draft'
            || $reviewStatus === 'draft';
    }
}
