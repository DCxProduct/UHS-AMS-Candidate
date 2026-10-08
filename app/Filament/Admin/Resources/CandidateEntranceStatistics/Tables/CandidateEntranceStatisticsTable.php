<?php

namespace App\Filament\Admin\Resources\CandidateEntranceStatistics\Tables;

use App\Support\WorkflowStageMessages;
use App\Support\StaticWorkflowMessages;
use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\CandidateEntranceStatisticResource;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\CandidateTypeResolver;
use App\Support\CandidateStatisticsSynchronizer;
use App\Support\FilamentActionPermissions;
use App\Support\FormEntryData;
use App\Support\CaseInsensitiveSearch;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use App\Support\NotificationLanguage;
use App\Support\UserTypeOptions;
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
                        self::resolveCandidateRole($record->creator, $record->data)
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

                TextColumn::make('name_khmer')
                    ->label(__('exam_results.name_khmer'))
                    ->getStateUsing(fn ($record): string => self::khmerName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyAny($query, [
                        'data->first_name_kh',
                        'data->last_name_kh',
                    ], $search))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('name_latin')
                    ->label(__('exam_results.name_latin'))
                    ->getStateUsing(fn ($record): string => self::latinName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyAny($query, [
                        'data->first_name_en',
                        'data->last_name_en',
                    ], $search))
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
                    ->getStateUsing(fn ($record) => data_get($record->data, 'candidate_status', 'pending'))
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'passed' => __('candidate_entrance_statistics.statuses.passed'),
                        default => __('candidate_entrance_statistics.statuses.pending'),
                    })
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
                            ->options(fn (): array => self::dynamicFormTypeOptions())
                            ->native(false)
                            ->live(),

                        Select::make('review_status')
                            ->label(__('candidate_entrance_statistics.review_status'))
                            ->options([
                                'pending' => self::statusLabel('pending'),
                                'passed' => self::statusLabel('passed'),
                            ])
                            ->native(false)
                            ->live(),

                        Select::make('user_type')
                            ->label(__('candidate_entrance_statistics.user_type'))
                            ->options(fn (): array => self::dynamicUserTypeOptions())
                            ->native(false)
                            ->searchable()
                            ->live(),

                        Select::make('major')
                            ->label(__('candidate_entrance_statistics.major'))
                            ->options(fn (): array => self::dynamicMajorOptions())
                            ->native(false)
                            ->searchable()
                            ->live(),

                        Select::make('reviewed_year')
                            ->label(__('candidate_entrance_statistics.reviewed_year'))
                            ->options(fn (): array => self::dynamicRequestReviewedYears())
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
                                function (Builder $query) use ($data): Builder {
                                    return match ($data['review_status']) {
                                        'passed' => $query->where('data->candidate_status', 'passed'),

                                        'pending' => $query->where(function (Builder $query): void {
                                            $query
                                                ->whereNull('data->candidate_status')
                                                ->orWhere('data->candidate_status', '')
                                                ->orWhere('data->candidate_status', 'pending');
                                        }),

                                        default => $query,
                                    };
                                }
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
                                            ->orWhereYear('reviewed_at', $data['reviewed_year'])
                                            ->orWhereRaw(
                                                "EXTRACT(YEAR FROM NULLIF(data->>'candidate_reviewed_at', '')::timestamp) = ?",
                                                [$data['reviewed_year']]
                                            );
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
                    ->label(__('candidate_entrance_statistics.statuses.passed'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('candidate_entrance_statistics.passed_confirm_title'))
                    ->modalDescription(__('candidate_entrance_statistics.passed_confirm_description'))
                    ->modalSubmitActionLabel(__('candidate_entrance_statistics.passed_confirm_yes'))
                    ->modalCancelActionLabel(__('candidate_entrance_statistics.passed_confirm_no'))
                    ->visible(fn (CustomFormEntry $record): bool => FilamentActionPermissions::canForResource(CandidateEntranceStatisticResource::class, 'passed')
                        && WorkflowStageMessages::canCurrentUserHandleStage($record, WorkflowStageType::AwaitingResults)
                        && strtolower((string) data_get($record->data, 'candidate_status', 'pending')) === 'pending')
                    ->action(function (CustomFormEntry $record): void {
                        FilamentActionPermissions::abortUnlessCanForResource(CandidateEntranceStatisticResource::class, 'passed');

                        self::markPassed($record);
                        WorkflowStageMessages::notifyResultStage($record);

                        Notification::make()
                            ->title(NotificationLanguage::trans('candidate_entrance_statistics.notifications.admin_passed_success_title'))
                            ->body(NotificationLanguage::trans('candidate_entrance_statistics.notifications.admin_passed_success_body'))
                            ->success()
                            ->send();
                    }),

                Action::make('pending')
                    ->label(__('candidate_entrance_statistics.actions.edit_result'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('candidate_entrance_statistics.pending_modal.heading'))
                    ->modalDescription(__('candidate_entrance_statistics.pending_modal.description'))
                    ->modalSubmitActionLabel(__('candidate_entrance_statistics.pending_modal.submit'))
                    ->modalCancelActionLabel(__('candidate_entrance_statistics.pending_modal.cancel'))
                    ->visible(fn (CustomFormEntry $record): bool => FilamentActionPermissions::canForResource(CandidateEntranceStatisticResource::class, 'pending')
                        && WorkflowStageMessages::canCurrentUserHandleStage($record, WorkflowStageType::AwaitingResults)
                        && strtolower((string) data_get($record->data, 'candidate_status', 'pending')) === 'passed'
                        && ! self::hasStudentReviewResultNotification($record, 'passed'))
                    ->action(function (CustomFormEntry $record): void {
                        FilamentActionPermissions::abortUnlessCanForResource(CandidateEntranceStatisticResource::class, 'pending');

                        self::markCandidatePending($record);

                        Notification::make()
                            ->title(NotificationLanguage::trans('candidate_entrance_statistics.actions.edit_result'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('bulk_passed')
                    ->label(__('candidate_entrance_statistics.statuses.passed'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->button()
                    ->visible(fn (): bool => FilamentActionPermissions::canForResource(CandidateEntranceStatisticResource::class, 'bulk_passed'))
                    ->requiresConfirmation()
                    ->modalHeading(__('candidate_entrance_statistics.passed_confirm_title'))
                    ->modalDescription(__('candidate_entrance_statistics.passed_confirm_description'))
                    ->modalSubmitActionLabel(__('candidate_entrance_statistics.passed_confirm_yes'))
                    ->modalCancelActionLabel(__('candidate_entrance_statistics.passed_confirm_no'))
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        FilamentActionPermissions::abortUnlessCanForResource(CandidateEntranceStatisticResource::class, 'bulk_passed');

                        $passedCount = 0;

                        $records->each(function (CustomFormEntry $record) use (&$passedCount): void {
                            if (strtolower((string) data_get($record->data, 'candidate_status', 'pending')) !== 'pending') {
                                return;
                            }

                            if (! WorkflowStageMessages::canCurrentUserHandleStage($record, WorkflowStageType::AwaitingResults)) {
                                return;
                            }

                            self::markPassed($record);
                            WorkflowStageMessages::notifyResultStage($record);
                            $passedCount++;
                        });

                        Notification::make()
                            ->title(NotificationLanguage::trans(
                                'candidate_entrance_statistics.notifications.bulk_passed_success_title',
                                ['count' => $passedCount]
                            ))
                            ->success()
                            ->send();
                    }),

                BulkAction::make('bulk_pending')
                    ->label(__('candidate_entrance_statistics.actions.edit_result'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->button()
                    ->visible(fn (): bool => FilamentActionPermissions::canForResource(CandidateEntranceStatisticResource::class, 'bulk_pending'))
                    ->requiresConfirmation()
                    ->modalHeading(__('candidate_entrance_statistics.pending_modal.heading'))
                    ->modalDescription(__('candidate_entrance_statistics.pending_modal.description'))
                    ->modalSubmitActionLabel(__('candidate_entrance_statistics.pending_modal.submit'))
                    ->modalCancelActionLabel(__('candidate_entrance_statistics.pending_modal.cancel'))
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        FilamentActionPermissions::abortUnlessCanForResource(CandidateEntranceStatisticResource::class, 'bulk_pending');

                        $editedCount = 0;

                        $records->each(function (CustomFormEntry $record) use (&$editedCount): void {
                            if (
                                strtolower((string) data_get($record->data, 'candidate_status', 'pending')) !== 'passed'
                                || self::hasStudentReviewResultNotification($record, 'passed')
                                || ! WorkflowStageMessages::canCurrentUserHandleStage($record, WorkflowStageType::AwaitingResults)
                            ) {
                                return;
                            }

                            self::markCandidatePending($record);
                            $editedCount++;
                        });

                        Notification::make()
                            ->title(NotificationLanguage::trans('candidate_entrance_statistics.actions.edit_result'))
                            ->body(__('candidate_entrance_statistics.notifications.bulk_pending_success_body', ['count' => $editedCount]))
                            ->success()
                            ->send();
                    }),
            ]);
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

    protected static function exportColumnDefinitions(): array
    {
        return [
            'row_number' => [
                'label' => __('exam_results.no'),
                'field_key' => 'row_number',
                'value' => fn (CustomFormEntry $record, int $rowNumber): string => (string) $rowNumber,
                'clean' => fn (CustomFormEntry $record, int $rowNumber): string => (string) $rowNumber,
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
                'value' => fn (CustomFormEntry $record): string => self::userTypeLabel(self::resolveCandidateRole($record->creator, $record->data)),
                'clean' => fn (CustomFormEntry $record): string => (string) self::resolveCandidateRole($record->creator, $record->data),
            ],
            'seat_number' => [
                'label' => __('exam_results.seat_number'),
                'field_key' => 'seat_number',
                'value' => fn (CustomFormEntry $record): string => self::entryValue($record, 'seat_number', self::entryValue($record, 'list_number', $record->creator?->seat_number)),
                'clean' => fn (CustomFormEntry $record): string => self::entryValue($record, 'seat_number', self::entryValue($record, 'list_number', $record->creator?->seat_number)),
            ],
            'name_khmer' => [
                'label' => __('exam_results.name_khmer'),
                'field_key' => 'name_khmer',
                'value' => fn (CustomFormEntry $record): string => self::khmerName($record),
                'clean' => fn (CustomFormEntry $record): string => self::khmerName($record),
            ],
            'name_latin' => [
                'label' => __('exam_results.name_latin'),
                'field_key' => 'name_latin',
                'value' => fn (CustomFormEntry $record): string => self::latinName($record),
                'clean' => fn (CustomFormEntry $record): string => self::latinName($record),
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
                'value' => fn (CustomFormEntry $record): string => (string) FormEntryData::firstFilled($record->data, FormEntryData::majorKeys(), '-'),
                'clean' => fn (CustomFormEntry $record): string => (string) FormEntryData::firstFilled($record->data, FormEntryData::majorKeys(), '-'),
            ],
            'date_of_birth' => [
                'label' => __('exam_results.date_of_birth'),
                'field_key' => 'date_of_birth',
                'value' => fn (CustomFormEntry $record): string => self::dateValue(self::entryValue($record, 'date_of_birth', $record->creator?->date_of_birth)),
                'clean' => fn (CustomFormEntry $record): string => self::entryValue($record, 'date_of_birth', $record->creator?->date_of_birth),
            ],
            'data.candidate_status' => [
                'label' => __('candidate_entrance_statistics.review_status_result'),
                'field_key' => 'candidate_status',
                'value' => fn (CustomFormEntry $record): string => match ((string) data_get($record->data, 'candidate_status', 'pending')) {
                    'passed' => __('candidate_entrance_statistics.statuses.passed'),
                    default => __('candidate_entrance_statistics.statuses.pending'),
                },
                'clean' => fn (CustomFormEntry $record): string => (string) data_get($record->data, 'candidate_status', 'pending'),
            ],
            'data.candidate_reviewed_at' => [
                'label' => __('candidate_entrance_statistics.reviewed_at'),
                'field_key' => 'candidate_reviewed_at',
                'value' => fn (CustomFormEntry $record): string => filled(data_get($record->data, 'candidate_reviewed_at'))
                    ? LocalizedDate::dayMonthYear((string) data_get($record->data, 'candidate_reviewed_at'))
                    : '-',
                'clean' => fn (CustomFormEntry $record): string => (string) data_get($record->data, 'candidate_reviewed_at', ''),
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

    protected static function dynamicRequestReviewedYears(): array
    {
        return CustomFormEntry::query()
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

    protected static function dynamicUserTypeOptions(): array
    {
        return CustomFormEntry::query()
            ->with('creator')
            ->get()
            ->flatMap(function (CustomFormEntry $entry): array {
                $role = self::resolveCandidateRole($entry->creator, $entry->data);

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
        $candidateType = CandidateTypeResolver::normalize($candidateType) ?? strtolower(trim($candidateType));
        $creatorIds = User::query()
            ->get()
            ->filter(fn (User $user): bool => CandidateTypeResolver::resolve($user) === $candidateType)
            ->modelKeys();

        return $query->where(function (Builder $query) use ($candidateType, $creatorIds): void {
            $hasCondition = false;

            if ($creatorIds !== []) {
                $query->whereIn('created_by', $creatorIds);
                $hasCondition = true;
            }

            foreach (['user_type', 'candidate_type', 'student_role'] as $key) {
                $method = $hasCondition ? 'orWhere' : 'where';
                $query->{$method}("data->{$key}", $candidateType);
                $hasCondition = true;
            }
        });
    }

    protected static function dynamicMajorOptions(): array
    {
        return CustomFormEntry::query()
            ->get(['data'])
            ->flatMap(function (CustomFormEntry $entry): array {
                return array_filter([
                    FormEntryData::firstFilled($entry->data, FormEntryData::majorKeys()),
                ], fn ($value): bool => filled($value));
            })
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $value): array => [$value => FormEntryData::majorOptionLabel($value, $value)])
            ->toArray();
    }

    protected static function dynamicFormTypeOptions(): array
    {
        $formIds = CandidateEntranceStatisticResource::getEloquentQuery()
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

        $dataJson['candidate_status'] = 'passed';
        $dataJson['registration_status'] = 'passed';
        $dataJson['exam_result'] = 'passed';
        $dataJson['result_status'] = 'passed';
        $dataJson['candidate_reviewed_at'] = now()->toDateTimeString();

        DB::table('custom_form_entries')
            ->where('id', $record->id)
            ->update([
                'data' => json_encode($dataJson, JSON_UNESCAPED_UNICODE),
                'review_status' => 'passed',
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        $record->refresh();
        CandidateStatisticsSynchronizer::syncPassedEntry($record);

        AuditLogger::log(
            action: 'passed',
            auditable: $record,
            oldValues: $oldValues,
            newValues: [
                'candidate_status' => 'passed',
                'registration_status' => 'passed',
                'review_status' => 'passed',
            ],
            description: 'Candidate result marked as passed',
            metadata: ['module' => 'Entrance Exam Statistics'],
        );
    }

    protected static function markCandidatePending(CustomFormEntry $record): void
    {
        $dataJson = self::normalizeData($record->data);
        $oldValues = [
            'candidate_status' => data_get($dataJson, 'candidate_status'),
            'review_status' => $record->review_status,
        ];
        $dataJson['candidate_status'] = 'pending';

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

    protected static function resolveCandidateRole(?User $user, array | object | null $data = null): ?string
    {
        return CandidateTypeResolver::resolve($user, $data);
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
        if (blank($state) || $state === '-') {
            return '-';
        }

        try {
            return Carbon::parse($state)->format('d-m-Y');
        } catch (\Throwable) {
            return (string) $state;
        }
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
}
