<?php

namespace App\Filament\Admin\Resources\ClosingDates\Pages;

use App\Filament\Admin\Resources\ClosingDates\ClosingDateResource;
use App\Support\AuditLogger;
use App\Support\FilamentActionPermissions;
use App\Support\SubmissionNextSteps;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ListClosingDates extends ListRecords
{
    protected static string $resource = ClosingDateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('closing_dates.create')),
            Action::make('submission_next_steps')
                ->label(__('submission_next_steps.settings'))
                ->icon('heroicon-o-document-text')
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(ClosingDateResource::class, 'update'))
                ->modalHeading(__('submission_next_steps.settings'))
                ->modalSubmitActionLabel(__('app.save'))
                ->schema([
                    Select::make('custom_form_id')
                        ->label(__('candidate_submit_popup_settings.fields.form'))
                        ->options(fn (): array => CustomForm::query()->where('is_active', true)->orderBy('display_order')->orderBy('id')->get()
                            ->mapWithKeys(fn (CustomForm $form): array => [$form->id => $form->display_name])->all())
                        ->required()
                        ->rules([Rule::exists('custom_forms', 'id')->whereNull('deleted_at')->where('is_active', true)])
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $settings = CustomForm::query()->where('is_active', true)->find($state)?->submission_next_steps ?? [];
                            $set('is_active', (bool) ($settings['is_active'] ?? false));
                            foreach (['heading_en', 'heading_km', 'title_en', 'title_km', 'message_en', 'message_km', 'continue_label_en', 'continue_label_km'] as $field) {
                                $set($field, $settings[$field] ?? null);
                            }
                        }),
                    Grid::make(['default' => 2])
                        ->schema([
                            TextInput::make('heading_en')
                                ->label(__('submission_next_steps.heading_en'))
                                ->placeholder(__('submission_next_steps.submitted'))
                                ->maxLength(255),
                            TextInput::make('heading_km')
                                ->label(__('submission_next_steps.heading_km'))
                                ->placeholder(__('submission_next_steps.submitted'))
                                ->maxLength(255),
                            TextInput::make('title_en')
                                ->label(__('candidate_submit_popup_settings.fields.title_en'))
                                ->placeholder(__('submission_next_steps.title'))
                                ->maxLength(255),
                            TextInput::make('title_km')
                                ->label(__('candidate_submit_popup_settings.fields.title_km'))
                                ->placeholder(__('submission_next_steps.title'))
                                ->maxLength(255),
                            Textarea::make('message_en')
                                ->label(__('submission_next_steps.message_en'))
                                ->rows(5)->maxLength(10000),
                            Textarea::make('message_km')
                                ->label(__('submission_next_steps.message_km'))
                                ->rows(5)->maxLength(10000),
                            TextInput::make('continue_label_en')
                                ->label(__('submission_next_steps.continue_label_en'))
                                ->placeholder(__('submission_next_steps.continue'))
                                ->maxLength(100),
                            TextInput::make('continue_label_km')
                                ->label(__('submission_next_steps.continue_label_km'))
                                ->placeholder(__('submission_next_steps.continue'))
                                ->maxLength(100),
                        ]),
                    Toggle::make('is_active')
                        ->label(__('submission_next_steps.enabled'))
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    FilamentActionPermissions::abortUnlessCanForResource(ClosingDateResource::class, 'update');
                    $settings = Arr::only($data, ['is_active', 'heading_en', 'heading_km', 'title_en', 'title_km', 'message_en', 'message_km', 'continue_label_en', 'continue_label_km']);
                    $form = SubmissionNextSteps::updateSettings((int) $data['custom_form_id'], $settings);
                    AuditLogger::log(
                        action: 'updated',
                        auditable: $form,
                        newValues: ['submission_next_steps' => $settings],
                        description: 'Updated candidate next steps after submission',
                        metadata: ['module' => 'Closing Dates'],
                    );
                    Notification::make()->title(__('submission_next_steps.saved'))->success()->send();
                }),
            Action::make('clear_data')
                ->label(__('app.clear_data'))
                ->color('danger')
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(ClosingDateResource::class, 'clear_data'))
                ->requiresConfirmation()
                ->modalHeading(__('app.clear_data'))
                ->modalDescription(__('closing_dates.clear_data_confirm'))
                ->modalSubmitActionLabel(__('app.clear_data'))
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
                )),
        ];
    }

    public function clearDataFromTableSelection(
        array $selectedRecordKeys = [],
        bool $isTrackingDeselectedRecords = false,
        array $deselectedRecordKeys = [],
    ): void {
        FilamentActionPermissions::abortUnlessCanForResource(ClosingDateResource::class, 'clear_data');

        $query = $this->getFilteredTableQuery();

        if ($isTrackingDeselectedRecords) {
            if (filled($deselectedRecordKeys)) {
                $query->whereKeyNot($deselectedRecordKeys);
            }
        } elseif (filled($selectedRecordKeys)) {
            $query->whereKey($selectedRecordKeys);
        }

        $count = $query->toBase()->delete();

        AuditLogger::log(
            action: 'cleared',
            description: 'Cleared Closing Dates ('.$count.' records)',
            metadata: ['module' => 'Closing Dates'],
        );

        $this->selectedTableRecords = [];
        $this->deselectedTableRecords = [];
        $this->isTrackingDeselectedTableRecords = false;
        $this->resetPage();
        $this->flushCachedTableRecords();
        $this->dispatch('$refresh');
    }
}
