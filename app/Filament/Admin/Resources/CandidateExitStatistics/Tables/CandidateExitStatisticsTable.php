<?php

namespace App\Filament\Admin\Resources\CandidateExitStatistics\Tables;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\CandidateExitStatistics\CandidateExitStatisticResource;
use App\Models\CandidateExitStatistic;
use App\Support\AuditLogger;
use App\Support\CandidateTypeResolver;
use App\Support\CandidateStatisticsSynchronizer;
use App\Support\CaseInsensitiveSearch;
use App\Support\FilamentActionPermissions;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use App\Support\WorkflowStageMessages;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class CandidateExitStatisticsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->checkIfRecordIsSelectableUsing(fn (CandidateExitStatistic $record): bool => in_array(
                strtolower((string) $record->candidate_status),
                ['pending', 'passed'],
                true,
            ))
            ->defaultSort('id', 'desc')
            ->recordUrl(null)
            ->recordAction(null)
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('candidate_exit_statistics.no'))
                    ->rowIndex()
                    ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('form_type')
                    ->label(__('candidate_exit_statistics.fields.form_type'))
                    ->getStateUsing(fn (CandidateExitStatistic $record): string => self::entryFormTypeLabel($record))
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('academic_year')
                    ->label(__('candidate_exit_statistics.fields.academic_year'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('user_type')
                    ->label(__('candidate_exit_statistics.fields.user_type'))
                    ->formatStateUsing(fn (?string $state): string => CandidateTypeResolver::label($state))
                    ->badge()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('seat_number')
                    ->label(__('candidate_exit_statistics.fields.seat_number'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('name_khmer')
                    ->label(__('candidate_exit_statistics.fields.name_khmer'))
                    ->getStateUsing(fn (CandidateExitStatistic $record): string => self::joinName($record->first_name_kh, $record->last_name_kh))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyAny($query, [
                        'first_name_kh',
                        'last_name_kh',
                    ], $search))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('name_latin')
                    ->label(__('candidate_exit_statistics.fields.name_latin'))
                    ->getStateUsing(fn (CandidateExitStatistic $record): string => self::joinName($record->first_name_en, $record->last_name_en))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyAny($query, [
                        'first_name_en',
                        'last_name_en',
                    ], $search))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('gender')
                    ->label(__('candidate_exit_statistics.fields.gender'))
                    ->formatStateUsing(fn (?string $state): string => self::optionLabel('gender', $state))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('major')
                    ->label(__('candidate_exit_statistics.fields.major'))
                    ->badge()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('date_of_birth')
                    ->label(__('candidate_exit_statistics.fields.date_of_birth'))
                    ->formatStateUsing(fn ($state): string => filled($state) ? LocalizedDate::dayMonthYear($state) : '-')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('candidate_status')
                    ->label(__('candidate_exit_statistics.fields.candidate_status'))
                    ->formatStateUsing(fn (?string $state): string => self::optionLabel('candidate_status', $state))
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'passed' ? 'success' : 'warning')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('candidate_reviewed_at')
                    ->label(__('candidate_exit_statistics.fields.candidate_reviewed_at'))
                    ->formatStateUsing(fn ($state): string => filled($state) ? LocalizedDate::dayMonthYear($state) : '-')
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->searchUsing(function (Builder $query, string $search): void {
                self::applyGlobalSearch($query, $search);
            })
            ->filters([
                Filter::make('candidate_exit_statistics_filters')
                    ->label(new HtmlString('&nbsp;'))
                    ->schema([
                        Select::make('form_type')
                            ->label(__('candidate_exit_statistics.filters.form_type'))
                            ->options(fn (): array => self::dynamicFormTypeOptions())
                            ->native(false)
                            ->live(),

                        Select::make('candidate_status')
                            ->label(__('candidate_exit_statistics.filters.review_status'))
                            ->options(__('candidate_exit_statistics.options.candidate_status'))
                            ->native(false)
                            ->live(),

                        Select::make('user_type')
                            ->label(__('candidate_exit_statistics.filters.user_type'))
                            ->options(fn (): array => self::distinctOptions('user_type'))
                            ->searchable()
                            ->native(false)
                            ->live(),

                        Select::make('major')
                            ->label(__('candidate_exit_statistics.filters.major'))
                            ->options(fn (): array => self::distinctOptions('major'))
                            ->searchable()
                            ->native(false)
                            ->live(),

                        Select::make('reviewed_year')
                            ->label(__('candidate_exit_statistics.filters.year'))
                            ->options(fn (): array => self::reviewedYearOptions())
                            ->native(false)
                            ->live(),
                    ])
                    ->columns(4)
                    ->columnSpanFull()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['form_type'] ?? null),
                                fn (Builder $query): Builder => self::applyFormTypeFilter($query, (string) $data['form_type'])
                            )
                            ->when(filled($data['candidate_status'] ?? null), fn (Builder $query): Builder => $query->where('candidate_status', $data['candidate_status']))
                            ->when(filled($data['user_type'] ?? null), fn (Builder $query): Builder => $query->where('user_type', $data['user_type']))
                            ->when(filled($data['major'] ?? null), fn (Builder $query): Builder => $query->where('major', $data['major']))
                            ->when(filled($data['reviewed_year'] ?? null), fn (Builder $query): Builder => $query->whereYear('candidate_reviewed_at', $data['reviewed_year']));
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns(4)
            ->recordActions([
                Action::make('passed')
                    ->label(__('candidate_exit_statistics.statuses.passed'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('candidate_exit_statistics.passed_confirm_title'))
                    ->modalDescription(__('candidate_exit_statistics.passed_confirm_description'))
                    ->modalSubmitActionLabel(__('candidate_exit_statistics.passed_confirm_yes'))
                    ->modalCancelActionLabel(__('candidate_exit_statistics.passed_confirm_no'))
                    ->visible(fn (CandidateExitStatistic $record): bool => FilamentActionPermissions::canForResource(CandidateExitStatisticResource::class, 'passed')
                        && WorkflowStageMessages::canCurrentUserHandleStage($record, WorkflowStageType::AwaitingResults)
                        && strtolower((string) $record->candidate_status) === 'pending')
                    ->action(function (CandidateExitStatistic $record): void {
                        FilamentActionPermissions::abortUnlessCanForResource(CandidateExitStatisticResource::class, 'passed');

                        self::markPassed($record);
                        $entry = CustomFormEntry::query()->find($record->custom_form_entry_id);

                        if ($entry !== null) {
                            WorkflowStageMessages::notifyResultStage($entry);
                        }

                        Notification::make()
                            ->title(__('candidate_exit_statistics.notifications.passed'))
                            ->success()
                            ->send();
                    }),

                Action::make('pending')
                    ->label(__('candidate_exit_statistics.actions.edit'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('candidate_exit_statistics.pending_modal.heading'))
                    ->modalDescription(__('candidate_exit_statistics.pending_modal.description'))
                    ->modalSubmitActionLabel(__('candidate_exit_statistics.pending_modal.submit'))
                    ->modalCancelActionLabel(__('candidate_exit_statistics.pending_modal.cancel'))
                    ->visible(fn (CandidateExitStatistic $record): bool => FilamentActionPermissions::canForResource(CandidateExitStatisticResource::class, 'pending')
                        && WorkflowStageMessages::canCurrentUserHandleStage($record, WorkflowStageType::AwaitingResults)
                        && strtolower((string) $record->candidate_status) === 'passed')
                    ->action(function (CandidateExitStatistic $record): void {
                        FilamentActionPermissions::abortUnlessCanForResource(CandidateExitStatisticResource::class, 'pending');

                        self::markCandidatePending($record);

                        Notification::make()
                            ->title(__('candidate_exit_statistics.notifications.pending'))
                            ->success()
                            ->send();
                    }),

            ])
            ->toolbarActions([
                BulkAction::make('bulk_passed')
                    ->label(__('candidate_exit_statistics.statuses.passed'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->button()
                    ->visible(fn (): bool => FilamentActionPermissions::canForResource(CandidateExitStatisticResource::class, 'bulk_passed'))
                    ->requiresConfirmation()
                    ->modalHeading(__('candidate_exit_statistics.passed_confirm_title'))
                    ->modalDescription(__('candidate_exit_statistics.passed_confirm_description'))
                    ->modalSubmitActionLabel(__('candidate_exit_statistics.passed_confirm_yes'))
                    ->modalCancelActionLabel(__('candidate_exit_statistics.passed_confirm_no'))
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        FilamentActionPermissions::abortUnlessCanForResource(CandidateExitStatisticResource::class, 'bulk_passed');

                        $passedCount = 0;

                        $records->each(function (CandidateExitStatistic $record) use (&$passedCount): void {
                            if (
                                strtolower((string) $record->candidate_status) !== 'pending'
                                || ! WorkflowStageMessages::canCurrentUserHandleStage($record, WorkflowStageType::AwaitingResults)
                            ) {
                                return;
                            }

                            self::markPassed($record);
                            $entry = CustomFormEntry::query()->find($record->custom_form_entry_id);

                            if ($entry !== null) {
                                WorkflowStageMessages::notifyResultStage($entry);
                            }
                            $passedCount++;
                        });

                        Notification::make()
                            ->title(__('candidate_exit_statistics.notifications.bulk_passed', ['count' => $passedCount]))
                            ->success()
                            ->send();
                    }),

                BulkAction::make('bulk_pending')
                    ->label(__('candidate_exit_statistics.actions.edit'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->button()
                    ->visible(fn (): bool => FilamentActionPermissions::canForResource(CandidateExitStatisticResource::class, 'bulk_pending'))
                    ->requiresConfirmation()
                    ->modalHeading(__('candidate_exit_statistics.pending_modal.heading'))
                    ->modalDescription(__('candidate_exit_statistics.pending_modal.description'))
                    ->modalSubmitActionLabel(__('candidate_exit_statistics.pending_modal.submit'))
                    ->modalCancelActionLabel(__('candidate_exit_statistics.pending_modal.cancel'))
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        FilamentActionPermissions::abortUnlessCanForResource(CandidateExitStatisticResource::class, 'bulk_pending');

                        $editedCount = 0;

                        $records->each(function (CandidateExitStatistic $record) use (&$editedCount): void {
                            if (
                                strtolower((string) $record->candidate_status) !== 'passed'
                                || ! WorkflowStageMessages::canCurrentUserHandleStage($record, WorkflowStageType::AwaitingResults)
                            ) {
                                return;
                            }

                            self::markCandidatePending($record);
                            $editedCount++;
                        });

                        Notification::make()
                            ->title(__('candidate_exit_statistics.notifications.pending'))
                            ->body(__('candidate_exit_statistics.notifications.bulk_pending', ['count' => $editedCount]))
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function downloadExcel(iterable $records, ?array $columnKeys = null)
    {
        $filename = 'candidate-exit-statistics-'.now()->format('Y-m-d-His').'.xlsx';
        $path = storage_path('app/'.uniqid('candidate-exit-statistics-', true).'.xlsx');

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

    protected static function markPassed(CandidateExitStatistic $record): void
    {
        $oldValues = [
            'candidate_status' => $record->candidate_status,
            'candidate_reviewed_at' => $record->candidate_reviewed_at,
        ];

        $record->forceFill([
            'candidate_status' => 'passed',
            'candidate_reviewed_at' => now(),
        ])->saveQuietly();

        $entry = CustomFormEntry::query()->find($record->custom_form_entry_id);

        if ($entry !== null && WorkflowStageMessages::hasConfiguredWorkflow($entry)) {
            $data = is_array($entry->data) ? $entry->data : [];
            $now = now();

            $data['candidate_status'] = 'passed';
            $data['registration_status'] = 'passed';
            $data['exam_result'] = 'passed';
            $data['result_status'] = 'passed';
            $data['candidate_reviewed_at'] = $now->toDateTimeString();

            DB::table('custom_form_entries')
                ->where('id', $entry->getKey())
                ->update([
                    'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'review_status' => 'passed',
                    'reviewed_at' => $now,
                    'updated_at' => $now,
                ]);

            $entry->refresh();
            CandidateStatisticsSynchronizer::syncPassedEntry($entry);
        }

        AuditLogger::log(
            action: 'passed',
            auditable: $record,
            oldValues: $oldValues,
            newValues: [
                'candidate_status' => 'passed',
                'candidate_reviewed_at' => $record->candidate_reviewed_at,
            ],
            description: 'Candidate entrance statistic marked as passed',
            metadata: ['module' => 'Candidate Entrance Statistics'],
        );
    }

    protected static function markCandidatePending(CandidateExitStatistic $record): void
    {
        $oldValues = [
            'candidate_status' => $record->candidate_status,
            'candidate_reviewed_at' => $record->candidate_reviewed_at,
        ];

        $record->forceFill([
            'candidate_status' => 'pending',
        ])->saveQuietly();

        $entry = CustomFormEntry::query()->find($record->custom_form_entry_id);

        if ($entry !== null && WorkflowStageMessages::hasConfiguredWorkflow($entry)) {
            $data = is_array($entry->data) ? $entry->data : [];
            $data['candidate_status'] = 'pending';

            DB::table('custom_form_entries')
                ->where('id', $entry->getKey())
                ->update([
                    'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);

            $entry->refresh();
            CandidateStatisticsSynchronizer::syncPendingEntry($entry);
        }

        AuditLogger::log(
            action: 'updated',
            auditable: $record,
            oldValues: $oldValues,
            newValues: [
                'candidate_status' => 'pending',
                'candidate_reviewed_at' => $record->candidate_reviewed_at,
            ],
            description: 'Candidate entrance statistic changed back to pending',
            metadata: ['module' => 'Candidate Entrance Statistics'],
        );
    }

    protected static function applyGlobalSearch(Builder $query, string $search): void
    {
        CaseInsensitiveSearch::applyAny($query, [
            'form_type',
            'academic_year',
            'user_type',
            'seat_number',
            'first_name_kh',
            'last_name_kh',
            'first_name_en',
            'last_name_en',
            'gender',
            'major',
            'candidate_status',
        ], $search);
    }

    protected static function excelRows(iterable $records, array $columnKeys): array
    {
        $rows = [self::excelHeadings($columnKeys)];
        $rowNumber = 1;

        foreach ($records as $record) {
            if ($record instanceof CandidateExitStatistic) {
                $rows[] = self::exportRow($record, $rowNumber++, $columnKeys);
            }
        }

        return $rows;
    }

    protected static function cleanDataRows(iterable $records, array $columnKeys): array
    {
        $rows = [self::cleanDataHeadings($columnKeys)];
        $rowNumber = 1;

        foreach ($records as $record) {
            if ($record instanceof CandidateExitStatistic) {
                $rows[] = self::cleanDataRow($record, $rowNumber++, $columnKeys);
            }
        }

        return $rows;
    }

    protected static function exportColumnDefinitions(): array
    {
        return [
            'row_number' => [
                'label' => __('candidate_exit_statistics.no'),
                'field_key' => 'row_number',
                'value' => fn (CandidateExitStatistic $record, int $rowNumber): string => (string) $rowNumber,
                'clean' => fn (CandidateExitStatistic $record, int $rowNumber): string => (string) $rowNumber,
            ],
            'form_type' => [
                'label' => __('candidate_exit_statistics.fields.form_type'),
                'field_key' => 'form_type',
                'value' => fn (CandidateExitStatistic $record): string => self::optionLabel('form_type', $record->form_type),
                'clean' => fn (CandidateExitStatistic $record): string => (string) $record->form_type,
            ],
            'academic_year' => [
                'label' => __('candidate_exit_statistics.fields.academic_year'),
                'field_key' => 'academic_year',
                'value' => fn (CandidateExitStatistic $record): string => (string) $record->academic_year,
                'clean' => fn (CandidateExitStatistic $record): string => (string) $record->academic_year,
            ],
            'user_type' => [
                'label' => __('candidate_exit_statistics.fields.user_type'),
                'field_key' => 'user_type',
                'value' => fn (CandidateExitStatistic $record): string => CandidateTypeResolver::label($record->user_type),
                'clean' => fn (CandidateExitStatistic $record): string => (string) $record->user_type,
            ],
            'seat_number' => [
                'label' => __('candidate_exit_statistics.fields.seat_number'),
                'field_key' => 'seat_number',
                'value' => fn (CandidateExitStatistic $record): string => (string) $record->seat_number,
                'clean' => fn (CandidateExitStatistic $record): string => (string) $record->seat_number,
            ],
            'name_khmer' => [
                'label' => __('candidate_exit_statistics.fields.name_khmer'),
                'field_key' => 'name_khmer',
                'value' => fn (CandidateExitStatistic $record): string => self::joinName($record->first_name_kh, $record->last_name_kh),
                'clean' => fn (CandidateExitStatistic $record): string => self::joinName($record->first_name_kh, $record->last_name_kh),
            ],
            'name_latin' => [
                'label' => __('candidate_exit_statistics.fields.name_latin'),
                'field_key' => 'name_latin',
                'value' => fn (CandidateExitStatistic $record): string => self::joinName($record->first_name_en, $record->last_name_en),
                'clean' => fn (CandidateExitStatistic $record): string => self::joinName($record->first_name_en, $record->last_name_en),
            ],
            'gender' => [
                'label' => __('candidate_exit_statistics.fields.gender'),
                'field_key' => 'gender',
                'value' => fn (CandidateExitStatistic $record): string => self::optionLabel('gender', $record->gender),
                'clean' => fn (CandidateExitStatistic $record): string => (string) $record->gender,
            ],
            'major' => [
                'label' => __('candidate_exit_statistics.fields.major'),
                'field_key' => 'major',
                'value' => fn (CandidateExitStatistic $record): string => (string) $record->major,
                'clean' => fn (CandidateExitStatistic $record): string => (string) $record->major,
            ],
            'date_of_birth' => [
                'label' => __('candidate_exit_statistics.fields.date_of_birth'),
                'field_key' => 'date_of_birth',
                'value' => fn (CandidateExitStatistic $record): string => LocalizedDate::dayMonthYear($record->date_of_birth),
                'clean' => fn (CandidateExitStatistic $record): string => (string) $record->date_of_birth,
            ],
            'candidate_status' => [
                'label' => __('candidate_exit_statistics.fields.candidate_status'),
                'field_key' => 'candidate_status',
                'value' => fn (CandidateExitStatistic $record): string => self::optionLabel('candidate_status', $record->candidate_status),
                'clean' => fn (CandidateExitStatistic $record): string => (string) $record->candidate_status,
            ],
            'candidate_reviewed_at' => [
                'label' => __('candidate_exit_statistics.fields.candidate_reviewed_at'),
                'field_key' => 'candidate_reviewed_at',
                'value' => fn (CandidateExitStatistic $record): string => LocalizedDate::dayMonthYear($record->candidate_reviewed_at),
                'clean' => fn (CandidateExitStatistic $record): string => (string) $record->candidate_reviewed_at,
            ],
        ];
    }

    protected static function exportRow(CandidateExitStatistic $record, int $rowNumber, array $columnKeys): array
    {
        $definitions = self::exportColumnDefinitions();

        return collect($columnKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $definitions))
            ->map(fn (string $key): string => $definitions[$key]['value']($record, $rowNumber))
            ->values()
            ->all();
    }

    protected static function cleanDataRow(CandidateExitStatistic $record, int $rowNumber, array $columnKeys): array
    {
        $definitions = self::exportColumnDefinitions();

        return collect($columnKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $definitions))
            ->map(fn (string $key): string => $definitions[$key]['clean']($record, $rowNumber))
            ->values()
            ->all();
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

    protected static function writeXlsx(string $path, array $sheets): void
    {
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', self::contentTypesXml($sheets));
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', self::workbookXml($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelsXml($sheets));
        $zip->addFromString('xl/styles.xml', self::stylesXml());

        foreach (array_values($sheets) as $index => $sheet) {
            $zip->addFromString(
                'xl/worksheets/sheet'.($index + 1).'.xml',
                self::worksheetXml($sheet['rows'])
            );
        }

        $zip->close();
    }

    protected static function worksheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $xml .= '<row r="'.$excelRow.'">';

            foreach (array_values($row) as $columnIndex => $value) {
                $cell = self::columnName($columnIndex + 1).$excelRow;
                $xml .= '<c r="'.$cell.'" s="1" t="inlineStr"><is><t>'.self::xmlValue($value).'</t></is></c>';
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    protected static function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    protected static function xmlValue(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    protected static function contentTypesXml(array $sheets): string
    {
        $overrides = collect(array_keys($sheets))
            ->map(fn (int $index): string => '<Override PartName="/xl/worksheets/sheet'.($index + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides
            .'</Types>';
    }

    protected static function workbookXml(array $sheets): string
    {
        $sheetXml = collect(array_values($sheets))
            ->map(fn (array $sheet, int $index): string => '<sheet name="'.self::xmlValue(self::sheetName($sheet['name'])).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheetXml.'</sheets>'
            .'</workbook>';
    }

    protected static function workbookRelsXml(array $sheets): string
    {
        $relationships = collect(array_keys($sheets))
            ->map(fn (int $index): string => '<Relationship Id="rId'.($index + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($index + 1).'.xml"/>')
            ->implode('');

        $styleRelationId = count($sheets) + 1;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$relationships
            .'<Relationship Id="rId'.$styleRelationId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    protected static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/><family val="2"/></font><font><sz val="11"/><name val="Battambang"/><family val="2"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    protected static function sheetName(string $name): string
    {
        return substr($name, 0, 31);
    }

    protected static function joinName(?string $firstName, ?string $lastName): string
    {
        return trim(implode(' ', array_filter([(string) $firstName, (string) $lastName], fn (string $value): bool => $value !== '')));
    }

    protected static function optionLabel(string $group, ?string $value): string
    {
        if (blank($value)) {
            return '-';
        }

        return (string) (__('candidate_exit_statistics.options.'.$group.'.'.$value) ?: $value);
    }

    protected static function entryFormTypeLabel(CandidateExitStatistic $record): string
    {
        $form = $record->customFormEntry?->customForm;

        return $form?->display_name ?: self::optionLabel('form_type', $record->form_type);
    }

    protected static function dynamicFormTypeOptions(): array
    {
        $formIds = CandidateExitStatisticResource::getEloquentQuery()
            ->reorder()
            ->whereHas('customFormEntry')
            ->with('customFormEntry:id,custom_form_id')
            ->get(['custom_form_entry_id'])
            ->pluck('customFormEntry.custom_form_id')
            ->filter()
            ->unique()
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
            $formId = (int) substr($formType, 5);

            if ($formId > 0) {
                return $query->whereHas(
                    'customFormEntry',
                    fn (Builder $entryQuery): Builder => $entryQuery->where('custom_form_id', $formId),
                );
            }
        }

        return $query->where('form_type', $formType);
    }

    protected static function formFilterValue(int $formId): string
    {
        return 'form:' . $formId;
    }

    protected static function distinctOptions(string $column): array
    {
        $values = CandidateExitStatistic::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->orderBy($column)
            ->pluck($column, $column)
            ->all();

        if ($column !== 'user_type') {
            return $values;
        }

        return collect($values)
            ->mapWithKeys(fn (string $value): array => [$value => CandidateTypeResolver::label($value)])
            ->all();
    }

    protected static function reviewedYearOptions(): array
    {
        return CandidateExitStatistic::query()
            ->whereNotNull('candidate_reviewed_at')
            ->pluck('candidate_reviewed_at')
            ->map(fn ($date): string => (string) Carbon::parse($date)->year)
            ->unique()
            ->sortDesc()
            ->mapWithKeys(fn (string $year): array => [$year => $year])
            ->all();
    }
}
