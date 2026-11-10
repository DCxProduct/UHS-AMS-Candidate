<?php

namespace App\Filament\Admin\Resources\CandidateEntranceStatistics\Tables;

use App\Support\WorkflowStageMessages;
use App\Support\WorkflowButtons;
use App\Support\WorkflowStageProgression;
use App\Support\WorkflowActionFeedback;
use App\Support\StaticWorkflowMessages;
use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\CandidateEntranceStatisticResource;
use App\Support\AuditLogger;
use App\Support\CandidateNumber;
use App\Support\CandidateTypeResolver;
use App\Support\CandidateStatisticsSynchronizer;
use App\Support\FilamentActionPermissions;
use App\Support\FormEntryData;
use App\Support\CaseInsensitiveSearch;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use App\Support\NotificationLanguage;
use App\Support\UserTypeOptions;
use App\Support\TableFilterOptions;
use Carbon\Carbon;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class CandidateEntranceStatisticsTable
{
    public static function downloadExcel(iterable $records, ?array $columnKeys = null)
    {
        $filename = 'candidate-lists-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/' . uniqid('candidate-lists-', true) . '.xlsx');

        $columnKeys ??= array_keys(self::exportColumnDefinitions());

        self::writeXlsx($path, [
            [
                'name' => 'Clean Data',
                'rows' => self::excelRows($records, $columnKeys),
            ],
            [
                'name' => 'Database Export',
                'rows' => self::cleanDataRows($records, $columnKeys),
            ],
        ]);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->checkIfRecordIsSelectableUsing(function (CustomFormEntry $record): bool {
                return in_array(
                    strtolower((string) data_get($record->data, 'candidate_status', 'pending')),
                    ['pending', 'passed'],
                    true,
                );
            })
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('exam_results.no'))
                    ->rowIndex()
                    ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('candidate_id')
                    ->label(__('candidate_entrance_statistics.student_id'))
                    ->formatStateUsing(fn (mixed $state): string => CandidateNumber::format($state))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('form_type')
                    ->label(__('candidate_entrance_statistics.form_type'))
                    ->getStateUsing(fn (CustomFormEntry $record): string => self::recordFormTypeLabel($record))
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('academic_year')
                    ->label(__('exam_results.academic_year'))
                    ->getStateUsing(fn ($record): string => FormEntryData::academicYearLabel(
                        ['academic_year' => self::entryValue($record, 'academic_year', $record->creator?->academic_year)]
                    ))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::apply($query, 'data->academic_year', $search))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('user_type')
                    ->label(__('candidate_entrance_statistics.user_type'))
                    ->getStateUsing(fn ($record): string => self::userTypeLabel(
                        CandidateTypeResolver::forEntry($record)
                    ))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('seat_number')
                    ->label(__('exam_results.seat_number'))
                    ->getStateUsing(fn ($record): string => self::entryValue($record, 'seat_number', self::entryValue($record, 'list_number', $record->creator?->seat_number)))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return CaseInsensitiveSearch::applyAny($query, [
                            'data->seat_number',
                            'data->list_number',
                        ], $search)->orWhereHas('creator', fn (Builder $userQuery): Builder => CaseInsensitiveSearch::apply($userQuery, 'seat_number', $search));
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('first_name_kh')
                    ->label(__('exam_results.first_name_khmer'))
                    ->getStateUsing(fn ($record): string => self::entryValue($record, 'first_name_kh'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::apply($query, 'data->first_name_kh', $search))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('last_name_kh')
                    ->label(__('exam_results.last_name_khmer'))
                    ->getStateUsing(fn ($record): string => self::entryValue($record, 'last_name_kh'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::apply($query, 'data->last_name_kh', $search))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('first_name_en')
                    ->label(__('exam_results.first_name_latin'))
                    ->getStateUsing(fn ($record): string => self::entryValue($record, 'first_name_en'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::apply($query, 'data->first_name_en', $search))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('last_name_en')
                    ->label(__('exam_results.last_name_latin'))
                    ->getStateUsing(fn ($record): string => self::entryValue($record, 'last_name_en'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::apply($query, 'data->last_name_en', $search))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('gender')
                    ->label(__('exam_results.gender'))
                    ->getStateUsing(fn ($record): string => self::genderLabel(self::entryValue($record, 'gender')))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('major')
                    ->label(__('candidate_entrance_statistics.major'))
                    ->badge()
                    ->getStateUsing(fn ($record): string => FormEntryData::majorLabel($record->data))
                    ->searchable(query: fn (Builder $query, string $search): Builder => FormEntryData::applyJsonLikeFilter($query, FormEntryData::majorKeys(), $search))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('date_of_birth')
                    ->label(__('exam_results.date_of_birth'))
                    ->getStateUsing(fn ($record): string => self::dateValue(self::entryValue($record, 'date_of_birth', $record->creator?->date_of_birth)))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('data.candidate_status')
                    ->label(__('candidate_entrance_statistics.review_status_result'))
                    ->badge()
                    ->getStateUsing(fn ($record) => data_get($record->data, 'candidate_status') ?: 'pending')
                    ->formatStateUsing(fn (?string $state, CustomFormEntry $record): string => self::resultStatusLabel($record, $state))
                    ->color(fn (?string $state) => match ($state) {
                        'passed' => 'success',
                        default => 'warning',
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('data.candidate_reviewed_at')
                    ->label(__('candidate_entrance_statistics.reviewed_at'))
                    ->getStateUsing(fn (CustomFormEntry $record): ?string => data_get($record->data, 'candidate_status') === 'passed'
                        ? data_get($record->data, 'candidate_reviewed_at')
                        : null)
                    ->formatStateUsing(fn ($state) => filled($state)
                        ? LocalizedDate::dayMonthYear($state)
                        : '-')
                    ->color('info')
                    ->sortable(false)
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->searchUsing(function (Builder $query, string $search): void {
                self::applyGlobalSearch($query, $search);
            })
            ->filters([
                Filter::make('application_review_filters')
                    ->label(new HtmlString('&nbsp;'))
                    ->schema([
                        Select::make('form_selection')
                            ->label(__('candidate_entrance_statistics.form_type'))
                            ->options(fn (HasTable $livewire): array => self::dynamicFormTypeOptions(TableFilterOptions::query($livewire, 'application_review_filters', 'form_selection')))
                            ->native(false)
                            ->live(),

                        Select::make('review_status')
                            ->label(__('candidate_entrance_statistics.review_status'))
                            ->options(fn (HasTable $livewire): array => self::dynamicReviewStatusOptions(TableFilterOptions::query($livewire, 'application_review_filters', 'review_status')))
                            ->native(false)
                            ->live(),

                        Select::make('user_type')
                            ->label(__('candidate_entrance_statistics.user_type'))
                            ->options(fn (HasTable $livewire): array => self::dynamicUserTypeOptions(TableFilterOptions::query($livewire, 'application_review_filters', 'user_type')))
                            ->native(false)
                            ->searchable()
                            ->live(),

                        Select::make('major')
                            ->label(__('candidate_entrance_statistics.major'))
                            ->options(fn (HasTable $livewire): array => self::dynamicMajorOptions(TableFilterOptions::query($livewire, 'application_review_filters', 'major')))
                            ->native(false)
                            ->searchable()
                            ->live(),

                        Select::make('reviewed_year')
                            ->label(__('candidate_entrance_statistics.reviewed_year'))
                            ->options(fn (HasTable $livewire): array => self::dynamicRequestReviewedYears(TableFilterOptions::query($livewire, 'application_review_filters', 'reviewed_year')))
                            ->native(false)
                            ->live(),
                    ])
                    ->columns(4)
                    ->columnSpanFull()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['form_selection'] ?? null),
                                fn (Builder $query): Builder => self::applyFormTypeFilter(
                                    $query,
                                    (string) $data['form_selection'],
                                )
                            )
                            ->when(
                                filled($data['review_status'] ?? null),
                                fn (Builder $query): Builder => self::applyReviewStatusFilter($query, (string) $data['review_status'])
                            )
                            ->when(
                                filled($data['user_type'] ?? null),
                                fn (Builder $query): Builder => self::applyCandidateTypeFilter(
                                    $query,
                                    (string) $data['user_type'],
                                )
                            )
                            ->when(
                                filled($data['major'] ?? null),
                                fn (Builder $query): Builder => FormEntryData::applyJsonExactFilter($query, FormEntryData::majorKeys(), $data['major'])
                            )
                            ->when(
                                filled($data['reviewed_year'] ?? null),
                                function (Builder $query) use ($data): Builder {
                                    return $query->where(function (Builder $query) use ($data): void {
                                        $query->whereYear('created_at', $data['reviewed_year'])
                                            ->orWhereYear('reviewed_at', $data['reviewed_year']);
                                        if ($query->getConnection()->getDriverName() === 'pgsql') {
                                            $query->orWhereRaw(
                                                "EXTRACT(YEAR FROM NULLIF(data->>'candidate_reviewed_at', '')::timestamp) = ?",
                                                [$data['reviewed_year']],
                                            );
                                        } else {
                                            $query->orWhereYear('data->candidate_reviewed_at', $data['reviewed_year']);
                                        }
                                    });
                                }
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
                Action::make('passed')
                    ->label(fn ($record): string => WorkflowButtons::label($record, 'passed', __('candidate_entrance_statistics.statuses.passed')))
                    ->icon(fn ($record): ?string => WorkflowButtons::icon($record, 'passed', 'heroicon-o-check-circle'))
                    ->color(fn ($record): string => WorkflowButtons::color($record, 'passed', 'success'))
                    ->requiresConfirmation()
                    ->modalHeading(__('candidate_entrance_statistics.passed_confirm_title'))
                    ->modalDescription(__('candidate_entrance_statistics.passed_confirm_description'))
                    ->modalSubmitActionLabel(__('candidate_entrance_statistics.passed_confirm_yes'))
                    ->modalCancelActionLabel(__('candidate_entrance_statistics.passed_confirm_no'))
                    ->visible(fn (CustomFormEntry $record): bool => self::canHandleResultAction($record, 'passed')
                        && self::hasResultStatus($record, passed: false))
                    ->action(function (CustomFormEntry $record): void {
                        abort_unless(self::changeResult($record, 'passed', passed: true), 409);
                        WorkflowStageMessages::notifyResultStage($record);

                        WorkflowActionFeedback::send(
                            Notification::make()
                                ->body(NotificationLanguage::trans('candidate_entrance_statistics.notifications.admin_passed_success_body'))
                                ->success(),
                            WorkflowActionFeedback::title($record, 'passed', NotificationLanguage::trans('candidate_entrance_statistics.notifications.admin_passed_success_title')),
                        );
                    }),

                Action::make('edit_result')
                    ->label(__('candidate_entrance_statistics.actions.edit_result'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('candidate_entrance_statistics.pending_modal.heading'))
                    ->modalDescription(__('candidate_entrance_statistics.pending_modal.description'))
                    ->modalSubmitActionLabel(__('candidate_entrance_statistics.pending_modal.submit'))
                    ->modalCancelActionLabel(__('candidate_entrance_statistics.pending_modal.cancel'))
                    ->visible(fn (CustomFormEntry $record): bool => self::canHandleResultAction($record, 'edit_result')
                        && ! self::hasStudentPassedNotification($record)
                        && self::hasResultStatus($record, passed: true))
                    ->action(function (CustomFormEntry $record): void {
                        abort_unless(self::changeResult($record, 'edit_result', passed: false), 409);

                        WorkflowActionFeedback::send(
                            Notification::make()->success(),
                            WorkflowActionFeedback::title($record, 'pending', NotificationLanguage::trans('candidate_entrance_statistics.actions.edit_result')),
                        );
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('bulk_passed')
                    ->label(__('candidate_entrance_statistics.statuses.passed'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->button()
                    ->visible(fn (): bool => self::hasAuthorizedResultAction('bulk_passed'))
                    ->requiresConfirmation()
                    ->modalHeading(__('candidate_entrance_statistics.passed_confirm_title'))
                    ->modalDescription(__('candidate_entrance_statistics.passed_confirm_description'))
                    ->modalSubmitActionLabel(__('candidate_entrance_statistics.passed_confirm_yes'))
                    ->modalCancelActionLabel(__('candidate_entrance_statistics.passed_confirm_no'))
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        abort_unless(FilamentActionPermissions::canAccessWorkflowActions(CandidateEntranceStatisticResource::class), 403);

                        $passedCount = 0;
                        $affectedEntries = [];

                        $records->each(function (CustomFormEntry $record) use (&$passedCount, &$affectedEntries): void {
                            if (! self::changeResult($record, 'bulk_passed', passed: true, skipUnauthorized: true)) {
                                return;
                            }
                            WorkflowStageMessages::notifyResultStage($record);
                            $affectedEntries[] = $record;
                            $passedCount++;
                        });

                        $summary = NotificationLanguage::trans(
                            'candidate_entrance_statistics.notifications.bulk_passed_success_title',
                            ['count' => $passedCount],
                        );
                        $title = WorkflowActionFeedback::bulkTitle($affectedEntries, 'passed', $summary);
                        WorkflowActionFeedback::send(
                            Notification::make()->body($title !== $summary ? $summary : null)->success(),
                            $title,
                        );
                    }),

                BulkAction::make('bulk_pending')
                    ->label(__('candidate_entrance_statistics.actions.edit_result'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->button()
                    ->visible(fn (): bool => self::hasAuthorizedResultAction('bulk_pending'))
                    ->requiresConfirmation()
                    ->modalHeading(__('candidate_entrance_statistics.pending_modal.heading'))
                    ->modalDescription(__('candidate_entrance_statistics.pending_modal.description'))
                    ->modalSubmitActionLabel(__('candidate_entrance_statistics.pending_modal.submit'))
                    ->modalCancelActionLabel(__('candidate_entrance_statistics.pending_modal.cancel'))
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        abort_unless(FilamentActionPermissions::canAccessWorkflowActions(CandidateEntranceStatisticResource::class), 403);

                        $editedCount = 0;
                        $affectedEntries = [];

                        $records->each(function (CustomFormEntry $record) use (&$editedCount, &$affectedEntries): void {
                            if (! self::changeResult($record, 'bulk_pending', passed: false, skipUnauthorized: true)) {
                                return;
                            }
                            $affectedEntries[] = $record;
                            $editedCount++;
                        });

                        WorkflowActionFeedback::send(
                            Notification::make()
                                ->body(__('candidate_entrance_statistics.notifications.bulk_pending_success_body', ['count' => $editedCount]))
                                ->success(),
                            WorkflowActionFeedback::bulkTitle($affectedEntries, 'pending', NotificationLanguage::trans('candidate_entrance_statistics.actions.edit_result')),
                        );
                    }),
            ]);
    }

    public static function hasResultStatus(CustomFormEntry $record, bool $passed): bool
    {
        return WorkflowStageProgression::enabled($record)
            ? (bool) data_get($record->data, 'workflow_progress.result_passed', false) === $passed
            : strtolower((string) data_get($record->data, 'candidate_status', 'pending')) === ($passed ? 'passed' : 'pending');
    }

    public static function canHandleResultAction(CustomFormEntry $record, string $action): bool
    {
        return FilamentActionPermissions::canForWorkflowStage(
            CandidateEntranceStatisticResource::class,
            $action,
            $record,
            WorkflowStageType::AwaitingResults,
        );
    }

    protected static function hasAuthorizedResultAction(string $action): bool
    {
        if (! FilamentActionPermissions::canAccessWorkflowActions(CandidateEntranceStatisticResource::class)) {
            return false;
        }

        if (FilamentActionPermissions::canForResource(CandidateEntranceStatisticResource::class, $action)) {
            return true;
        }

        $passed = $action !== 'bulk_passed';

        return CandidateEntranceStatisticResource::getEloquentQuery()->lazy()->contains(
            fn (CustomFormEntry $record): bool => self::hasResultStatus($record, $passed)
                && WorkflowStageMessages::hasConfiguredWorkflow($record)
                && self::canHandleResultAction($record, $action),
        );
    }

    /** Recheck each selected application under its own lock before changing its result. */
    protected static function changeResult(CustomFormEntry $record, string $action, bool $passed, bool $skipUnauthorized = false): bool
    {
        return DB::transaction(function () use ($record, $action, $passed, $skipUnauthorized): bool {
            $current = CandidateEntranceStatisticResource::getEloquentQuery()
                ->lockForUpdate()->find($record->getKey());
            if ($current === null || ! self::canHandleResultAction($current, $action)) {
                abort_unless($skipUnauthorized, 403);

                return false;
            }

            if (! self::hasResultStatus($current, ! $passed)
                || (! $passed && self::hasStudentPassedNotification($current))) {
                return false;
            }

            $record->setRawAttributes($current->getAttributes(), true)->unsetRelations();
            if ($passed) {
                self::markPassed($record);
            } else {
                self::markCandidatePending($record);
            }

            return true;
        });
    }

    /** Apply an imported result through the same authorization and locking as Bulk Passed. */
    public static function markImportedPassed(CustomFormEntry $record): bool
    {
        return self::changeResult($record, 'bulk_passed', passed: true, skipUnauthorized: true);
    }

    protected static function applyGlobalSearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $query) use ($search): void {
            CaseInsensitiveSearch::applyAny($query, [
                'data->academic_year',
                'data->selected_academic_year',
                'data->seat_number',
                'data->list_number',
                'data->name_khmer',
                'data->name_latin',
                'data->first_name_kh',
                'data->last_name_kh',
                'data->first_name_en',
                'data->last_name_en',
                ...array_map(fn (string $key): string => "data->{$key}", FormEntryData::majorKeys()),
            ], $search);

            $query->orWhereHas('creator', function (Builder $creatorQuery) use ($search): void {
                CaseInsensitiveSearch::applyAny($creatorQuery, ['seat_number', 'name', 'name_latin'], $search);
            });
        });
    }

    protected static function excelRows(iterable $records, ?array $columnKeys = null): array
    {
        $columnKeys ??= array_keys(self::exportColumnDefinitions());
        $rows = [self::excelHeadings($columnKeys)];
        $rowNumber = 1;

        foreach ($records as $record) {
            if (! $record instanceof CustomFormEntry) {
                continue;
            }

            $rows[] = self::exportRow($record, $rowNumber++, $columnKeys);
        }

        return $rows;
    }

    protected static function cleanDataRows(iterable $records, array $columnKeys): array
    {
        $rows = [self::cleanDataHeadings($columnKeys)];
        $rowNumber = 1;

        foreach ($records as $record) {
            if (! $record instanceof CustomFormEntry) {
                continue;
            }

            $rows[] = self::cleanDataRow($record, $rowNumber++, $columnKeys);
        }

        return $rows;
    }

    protected static function writeXlsx(string $path, array $sheets): void
    {
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', self::contentTypesXml($sheets));
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', self::workbookXml($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelsXml($sheets));

        foreach (array_values($sheets) as $index => $sheet) {
            $zip->addFromString(
                'xl/worksheets/sheet' . ($index + 1) . '.xml',
                self::worksheetXml($sheet['rows'])
            );
        }

        $zip->close();
    }

    protected static function worksheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $xml .= '<row r="' . $excelRow . '">';

            foreach (array_values($row) as $columnIndex => $value) {
                $cell = self::columnName($columnIndex + 1) . $excelRow;
                $xml .= '<c r="' . $cell . '" t="inlineStr"><is><t>' . self::xmlValue($value) . '</t></is></c>';
            }

            $xml .= '</row>';
        }

        return $xml . '</sheetData></worksheet>';
    }

    protected static function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    protected static function xmlValue(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    protected static function excelHeadings(array $columnKeys): array
    {
        $definitions = self::exportColumnDefinitions();

        return collect($columnKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $definitions))
            ->map(fn (string $key): string => $definitions[$key]['label'])
            ->values()
            ->all();
    }

    protected static function cleanDataHeadings(array $columnKeys): array
    {
        $definitions = self::exportColumnDefinitions();

        return collect($columnKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $definitions))
            ->map(fn (string $key): string => $definitions[$key]['field_key'])
            ->values()
            ->all();
    }

    protected static function exportRow(CustomFormEntry $record, int $rowNumber, array $columnKeys): array
    {
        $definitions = self::exportColumnDefinitions();

        return collect($columnKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $definitions))
            ->map(fn (string $key): string => $definitions[$key]['value']($record, $rowNumber))
            ->values()
            ->all();
    }

    protected static function cleanDataRow(CustomFormEntry $record, int $rowNumber, array $columnKeys): array
    {
        $definitions = self::exportColumnDefinitions();

        return collect($columnKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $definitions))
            ->map(fn (string $key): string => $definitions[$key]['clean']($record, $rowNumber))
            ->values()
            ->all();
    }

    public static function exportColumnDefinitions(): array
    {
        return [
            'row_number' => [
                'label' => __('exam_results.no'),
                'field_key' => 'row_number',
                'value' => fn (CustomFormEntry $record, int $rowNumber): string => (string) $rowNumber,
                'clean' => fn (CustomFormEntry $record, int $rowNumber): string => (string) $rowNumber,
            ],
            'candidate_id' => [
                'label' => __('candidate_entrance_statistics.student_id'),
                'field_key' => 'candidate_id',
                'value' => fn (CustomFormEntry $record): string => CandidateNumber::format($record->candidate_id),
                'clean' => fn (CustomFormEntry $record): string => CandidateNumber::format($record->candidate_id),
            ],
            'form_type' => [
                'label' => __('candidate_entrance_statistics.form_type'),
                'field_key' => 'form_type',
                'value' => fn (CustomFormEntry $record): string => self::recordFormTypeLabel($record),
                'clean' => fn (CustomFormEntry $record): string => (string) data_get($record->data, 'form_selection', ''),
            ],
            'academic_year' => [
                'label' => __('exam_results.academic_year'),
                'field_key' => 'academic_year',
                'value' => fn (CustomFormEntry $record): string => FormEntryData::academicYearLabel(
                    ['academic_year' => self::entryValue($record, 'academic_year', $record->creator?->academic_year)]
                ),
                'clean' => fn (CustomFormEntry $record): string => self::entryValue($record, 'academic_year', $record->creator?->academic_year),
            ],
            'user_type' => [
                'label' => __('candidate_entrance_statistics.user_type'),
                'field_key' => 'user_type',
                'value' => fn (CustomFormEntry $record): string => self::userTypeLabel(CandidateTypeResolver::forEntry($record)),
                'clean' => fn (CustomFormEntry $record): string => (string) CandidateTypeResolver::forEntry($record),
            ],
            'seat_number' => [
                'label' => __('exam_results.seat_number'),
                'field_key' => 'seat_number',
                'value' => fn (CustomFormEntry $record): string => self::entryValue($record, 'seat_number', self::entryValue($record, 'list_number', $record->creator?->seat_number)),
                'clean' => fn (CustomFormEntry $record): string => self::entryValue($record, 'seat_number', self::entryValue($record, 'list_number', $record->creator?->seat_number)),
            ],
            'first_name_kh' => [
                'label' => __('exam_results.first_name_khmer'),
                'field_key' => 'first_name_kh',
                'value' => fn (CustomFormEntry $record): string => self::entryValue($record, 'first_name_kh'),
                'clean' => fn (CustomFormEntry $record): string => self::entryValue($record, 'first_name_kh'),
            ],
            'last_name_kh' => [
                'label' => __('exam_results.last_name_khmer'),
                'field_key' => 'last_name_kh',
                'value' => fn (CustomFormEntry $record): string => self::entryValue($record, 'last_name_kh'),
                'clean' => fn (CustomFormEntry $record): string => self::entryValue($record, 'last_name_kh'),
            ],
            'first_name_en' => [
                'label' => __('exam_results.first_name_latin'),
                'field_key' => 'first_name_en',
                'value' => fn (CustomFormEntry $record): string => self::entryValue($record, 'first_name_en'),
                'clean' => fn (CustomFormEntry $record): string => self::entryValue($record, 'first_name_en'),
            ],
            'last_name_en' => [
                'label' => __('exam_results.last_name_latin'),
                'field_key' => 'last_name_en',
                'value' => fn (CustomFormEntry $record): string => self::entryValue($record, 'last_name_en'),
                'clean' => fn (CustomFormEntry $record): string => self::entryValue($record, 'last_name_en'),
            ],
            'gender' => [
                'label' => __('exam_results.gender'),
                'field_key' => 'gender',
                'value' => fn (CustomFormEntry $record): string => self::genderLabel(self::entryValue($record, 'gender')),
                'clean' => fn (CustomFormEntry $record): string => self::entryValue($record, 'gender'),
            ],
            'major' => [
                'label' => __('candidate_entrance_statistics.major'),
                'field_key' => 'major',
                'value' => fn (CustomFormEntry $record): string => FormEntryData::majorLabel($record->data),
                'clean' => fn (CustomFormEntry $record): string => (string) FormEntryData::firstFilled($record->data, FormEntryData::majorKeys(), '-'),
            ],
            'date_of_birth' => [
                'label' => __('exam_results.date_of_birth'),
                'field_key' => 'date_of_birth',
                'value' => fn (CustomFormEntry $record): string => self::dateValue(self::entryValue($record, 'date_of_birth', $record->creator?->date_of_birth)),
                'clean' => fn (CustomFormEntry $record): string => self::dateValue(self::entryValue($record, 'date_of_birth', $record->creator?->date_of_birth)),
            ],
            'data.candidate_status' => [
                'label' => __('candidate_entrance_statistics.review_status_result'),
                'field_key' => 'candidate_status',
                'value' => fn (CustomFormEntry $record): string => self::resultStatusLabel($record, data_get($record->data, 'candidate_status')),
                'clean' => fn (CustomFormEntry $record): string => (string) data_get($record->data, 'candidate_status', 'pending'),
            ],
        ];
    }

    protected static function contentTypesXml(array $sheets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';

        foreach (array_values($sheets) as $index => $sheet) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . ($index + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return $xml . '</Types>';
    }

    protected static function workbookXml(array $sheets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';

        foreach (array_values($sheets) as $index => $sheet) {
            $xml .= '<sheet name="' . self::xmlValue($sheet['name']) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
        }

        return $xml . '</sheets></workbook>';
    }

    protected static function workbookRelsXml(array $sheets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        foreach (array_values($sheets) as $index => $sheet) {
            $xml .= '<Relationship Id="rId' . ($index + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($index + 1) . '.xml"/>';
        }

        return $xml . '</Relationships>';
    }

    protected static function dynamicRequestReviewedYears(?Builder $query = null): array
    {
        return ($query ?? CandidateEntranceStatisticResource::getEloquentQuery())
            ->get(['created_at', 'reviewed_at', 'data'])
            ->flatMap(function (CustomFormEntry $entry): array {
                $years = [];

                if ($entry->created_at) {
                    $years[] = Carbon::parse($entry->created_at)->format('Y');
                }

                if ($entry->reviewed_at) {
                    $years[] = Carbon::parse($entry->reviewed_at)->format('Y');
                }

                $candidateReviewedAt = data_get($entry->data, 'candidate_reviewed_at');

                if ($candidateReviewedAt) {
                    $years[] = Carbon::parse($candidateReviewedAt)->format('Y');
                }

                return $years;
            })
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn ($year): array => [(string) $year => (string) $year])
            ->toArray();
    }

    protected static function dynamicUserTypeOptions(?Builder $query = null): array
    {
        return ($query ?? CandidateEntranceStatisticResource::getEloquentQuery())
            ->with(['creator', 'customForm'])
            ->get()
            ->flatMap(function (CustomFormEntry $entry): array {
                $role = CandidateTypeResolver::forEntry($entry);

                return filled($role) ? [$role] : [];
            })
            ->map(fn ($value): string => strtolower(trim((string) $value)))
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $value): array => [$value => UserTypeOptions::formatLabel($value)])
            ->toArray();
    }

    protected static function applyCandidateTypeFilter(Builder $query, string $candidateType): Builder
    {
        return CandidateTypeResolver::filterEntries($query, $candidateType);
    }

    protected static function dynamicMajorOptions(?Builder $query = null): array
    {
        return ($query ?? CandidateEntranceStatisticResource::getEloquentQuery())
            ->get(['data'])
            ->flatMap(function (CustomFormEntry $entry): array {
                return FormEntryData::majorValues($entry->data);
            })
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $value): array => [$value => FormEntryData::majorOptionLabel($value, $value)])
            ->toArray();
    }

    protected static function dynamicFormTypeOptions(?Builder $query = null): array
    {
        $formIds = ($query ?? CandidateEntranceStatisticResource::getEloquentQuery())
            ->reorder()
            ->distinct()
            ->pluck('custom_form_id')
            ->filter()
            ->values();

        return CustomForm::query()
            ->where('is_active', true)
            ->where('slug', '!=', 'profile')
            ->whereIn('id', $formIds)
            ->orderBy('id')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (CustomForm $form): array => [
                self::formFilterValue((int) $form->id) => $form->display_name,
            ])
            ->all();
    }

    protected static function applyFormTypeFilter(Builder $query, string $formType): Builder
    {
        if (str_starts_with($formType, 'form:')) {
            $formId = self::formIdFromFilterValue($formType);

            if ($formId) {
                return $query->where('custom_form_id', $formId);
            }
        }

        return $query->where('data->form_selection', $formType);
    }

    protected static function formFilterValue(int $formId): string
    {
        return 'form:' . $formId;
    }

    protected static function formIdFromFilterValue(string $value): ?int
    {
        if (! str_starts_with($value, 'form:')) {
            return null;
        }

        $formId = (int) substr($value, 5);

        return $formId > 0 ? $formId : null;
    }

    protected static function candidateStatusQuery(Builder $query): Builder
    {
        return $query->whereIn('review_status', [
            'passed',
            'accepted',
            'approved',
        ]);
    }

    protected static function normalizeData(mixed $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        $decoded = json_decode((string) $data, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected static function markPassed(CustomFormEntry $record): void
    {
        $dataJson = self::normalizeData($record->data);
        $oldValues = [
            'candidate_status' => data_get($dataJson, 'candidate_status'),
            'registration_status' => data_get($dataJson, 'registration_status'),
            'review_status' => $record->review_status,
        ];

        $ordered = WorkflowStageProgression::enabled($record);
        if (! $ordered) {
            $dataJson['candidate_status'] = 'passed';
            $dataJson['registration_status'] = 'passed';
        }
        $dataJson['exam_result'] = 'passed';
        $dataJson['result_status'] = 'passed';
        $dataJson['candidate_reviewed_at'] = now()->toDateTimeString();

        $updates = [
            'data' => json_encode($dataJson, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ];
        if (! $ordered) {
            $updates['review_status'] = 'passed';
            $updates['reviewed_at'] = now();
        }
        DB::table('custom_form_entries')->where('id', $record->id)->update($updates);

        $record->refresh();
        if (WorkflowStageProgression::enabled($record)) {
            WorkflowStageProgression::passed($record);
        }
        CandidateStatisticsSynchronizer::syncPassedEntry($record);

        AuditLogger::log(
            action: 'passed',
            auditable: $record,
            oldValues: $oldValues,
            newValues: [
                'candidate_status' => data_get($record->data, 'candidate_status'),
                'registration_status' => data_get($record->data, 'registration_status'),
                'review_status' => $record->review_status,
            ],
            description: 'Candidate result marked as passed',
            metadata: ['module' => 'Entrance Exam Statistics'],
        );
    }

    public static function markCandidatePending(CustomFormEntry $record): void
    {
        $dataJson = self::normalizeData($record->data);
        $oldValues = [
            'candidate_status' => data_get($dataJson, 'candidate_status'),
            'candidate_reviewed_at' => data_get($dataJson, 'candidate_reviewed_at'),
            'review_status' => $record->review_status,
        ];
        $dataJson['candidate_status'] = 'pending';
        $dataJson['candidate_reviewed_at'] = null;

        DB::table('custom_form_entries')
            ->where('id', $record->id)
            ->update([
                'data' => json_encode($dataJson, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        $record->refresh();
        CandidateStatisticsSynchronizer::syncPendingEntry($record);

        AuditLogger::log(
            action: 'updated',
            auditable: $record,
            oldValues: $oldValues,
            newValues: [
                'candidate_status' => 'pending',
                'review_status' => $record->review_status,
            ],
            description: 'Candidate result changed back to pending',
            metadata: ['module' => 'Entrance Exam Statistics'],
        );
    }

    protected static function recordFormTypeLabel(CustomFormEntry $record): string
    {
        $form = $record->customForm;

        if ($form) {
            return $form->display_name;
        }

        return '-';
    }

    protected static function userTypeLabel(?string $state): string
    {
        return CandidateTypeResolver::label($state);
    }

    protected static function dynamicReviewStatusOptions(?Builder $query = null): array
    {
        return ($query ?? CandidateEntranceStatisticResource::getEloquentQuery())
            ->get()
            ->map(fn (CustomFormEntry $record): string => self::resultStatusLabel($record, data_get($record->data, 'candidate_status') ?: 'pending'))
            ->unique()
            ->mapWithKeys(fn (string $label): array => ['label:'.sha1($label) => $label])
            ->all();
    }

    protected static function applyReviewStatusFilter(Builder $query, string $status): Builder
    {
        if (str_starts_with($status, 'label:')) {
            $ids = (clone $query)->get()
                ->filter(function (CustomFormEntry $record) use ($status): bool {
                    $label = self::resultStatusLabel($record, data_get($record->data, 'candidate_status') ?: 'pending');

                    return 'label:'.sha1($label) === $status;
                })
                ->modelKeys();

            return $query->whereKey($ids);
        }

        return match ($status) {
            'passed' => $query->where('data->candidate_status', 'passed'),
            'pending' => $query->where(function (Builder $query): void {
                $query->whereNull('data->candidate_status')
                    ->orWhere('data->candidate_status', '')
                    ->orWhere('data->candidate_status', 'pending');
            }),
            default => $query,
        };
    }

    protected static function statusLabel(?string $state): string
    {
        return match ($state) {
            'passed', 'accepted' => __('candidate_entrance_statistics.statuses.passed'),
            default => __('candidate_entrance_statistics.statuses.pending'),
        };
    }

    protected static function actionLabel(string $action): string
    {
        return match ($action) {
            'passed' => __('candidate_entrance_statistics.statuses.passed'),
            default => __('candidate_entrance_statistics.statuses.pending'),
        };
    }

    protected static function entryValue($record, string $key, mixed $fallback = null): string
    {
        $value = data_get($record->data, $key);

        if (blank($value)) {
            $value = $fallback;
        }

        return blank($value) ? '-' : (string) $value;
    }

    protected static function khmerName($record): string
    {
        $name = trim(collect([
            data_get($record->data, 'first_name_kh'),
            data_get($record->data, 'last_name_kh'),
        ])->filter()->join(' '));

        return filled($name) ? $name : self::entryValue($record, 'name_khmer', $record->creator?->name);
    }

    protected static function latinName($record): string
    {
        $name = trim(collect([
            data_get($record->data, 'first_name_en'),
            data_get($record->data, 'last_name_en'),
        ])->filter()->join(' '));

        return filled($name) ? strtoupper($name) : self::entryValue($record, 'name_latin', $record->creator?->name_latin);
    }

    protected static function genderLabel(string $state): string
    {
        return match (strtolower($state)) {
            'male' => __('candidate_entrance_statistics.genders.male'),
            'female' => __('candidate_entrance_statistics.genders.female'),
            default => $state,
        };
    }

    protected static function dateValue(mixed $state): string
    {
        return LocalizedDate::dayMonthYear($state);
    }

    protected static function resultStatusLabel(CustomFormEntry $record, ?string $status): string
    {
        if ($status === 'passed') {
            return WorkflowStageMessages::passedResultStatusLabel($record)
                ?? __('candidate_entrance_statistics.statuses.passed');
        }

        if (blank($status) || $status === 'pending') {
            return WorkflowStageMessages::waitingResultStatusLabel($record)
                ?? __('candidate_entrance_statistics.statuses.pending');
        }

        return __('candidate_entrance_statistics.statuses.pending');
    }

    public static function notifyStudentReviewResult(
        CustomFormEntry $record,
        string $status,
        ?string $note = null,
        ?WorkflowStageType $notificationStage = null,
        bool $force = false,
    ): bool
    {
        $stageType = $status === 'passed'
            ? ($notificationStage ?? WorkflowStageMessages::resultStageFor($record) ?? WorkflowStageType::AwaitingResults)
            : WorkflowStageType::Rejected;

        if (! WorkflowStageMessages::hasConfiguredWorkflow($record)) {
            return StaticWorkflowMessages::notifyReviewResult($record, $status, $note, $force);
        }

        if (! $force && WorkflowStageMessages::hasSentNotification($record, $stageType)) {
            return false;
        }

        return $status === 'passed'
            ? WorkflowStageMessages::notifyConfiguredStage($record, $stageType, replaceExisting: $force)
            : WorkflowStageMessages::notify($record, $stageType);
    }

    public static function hasStudentReviewResultNotification(
        CustomFormEntry $record,
        string $status,
        ?WorkflowStageType $notificationStage = null,
    ): bool
    {
        if (! WorkflowStageMessages::hasConfiguredWorkflow($record)) {
            return StaticWorkflowMessages::hasReviewResultNotification($record, $status);
        }

        return WorkflowStageMessages::hasSentNotification(
            $record,
            $status === 'passed'
                ? ($notificationStage ?? WorkflowStageMessages::resultStageFor($record) ?? WorkflowStageType::AwaitingResults)
                : WorkflowStageType::Rejected,
        );
    }

    public static function hasStudentPassedNotification(CustomFormEntry $record): bool
    {
        return self::hasStudentReviewResultNotification($record, 'passed', WorkflowStageType::AwaitingResults)
            || self::hasStudentReviewResultNotification($record, 'passed', WorkflowStageType::Completed);
    }
}
