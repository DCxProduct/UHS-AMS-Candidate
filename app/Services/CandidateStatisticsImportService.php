<?php

namespace App\Services;

use App\Filament\Admin\Resources\CandidateEntranceStatistics\CandidateEntranceStatisticResource;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Tables\CandidateEntranceStatisticsTable;
use App\Filament\Admin\Resources\CandidateExitStatistics\CandidateExitStatisticResource;
use App\Filament\Admin\Resources\CandidateExitStatistics\Tables\CandidateExitStatisticsTable;
use App\Support\AuditLogger;
use App\Support\CandidateNumber;
use App\Support\FilamentActionPermissions;
use App\Support\SpreadsheetRowReader;
use App\Support\WorkflowStageMessages;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Traits\Localizable;
use Illuminate\Validation\ValidationException;

class CandidateStatisticsImportService
{
    use Localizable;

    private const IDENTITY_FIELDS = [
        'candidate_id', 'form_type', 'academic_year', 'user_type', 'seat_number',
        'name_khmer', 'name_latin', 'gender', 'major', 'date_of_birth',
        'first_name_kh', 'last_name_kh', 'first_name_en', 'last_name_en',
    ];

    /** Read the real headers for the modal; submit always reads them again. */
    public function previewColumns(UploadedFile $file, string $resourceClass, ?string $worksheetName = null): array
    {
        $table = $this->authorizedTable($resourceClass);
        $parsed = $this->mappedColumns(
            app(SpreadsheetRowReader::class)->read($file, $worksheetName),
            $this->localizedDefinitions($table),
        );

        $columns = $parsed['columns'];
        $templateKeys = array_column($table::exportColumnDefinitions(), 'field_key');
        $uploadedKeys = array_column($columns, 'key');
        $isExportTemplate = count($uploadedKeys) === count($templateKeys)
            && array_diff($uploadedKeys, $templateKeys) === []
            && array_diff($templateKeys, $uploadedKeys) === [];

        // Hide reference/result switches for this template only. A different
        // upload still receives a switch for each of its own column headings.
        if ($isExportTemplate) {
            $columns = array_values(array_filter($columns, fn (array $column): bool => ! in_array($column['key'], ['row_number', 'candidate_status'], true)));
        }

        return $columns;
    }

    private function authorizedTable(string $resourceClass): string
    {
        $table = match ($resourceClass) {
            CandidateEntranceStatisticResource::class => CandidateEntranceStatisticsTable::class,
            CandidateExitStatisticResource::class => CandidateExitStatisticsTable::class,
            default => abort(403),
        };
        abort_unless(FilamentActionPermissions::canAccessWorkflowActions($resourceClass), 403);
        FilamentActionPermissions::abortUnlessCanForResource($resourceClass, 'import_excel');

        return $table;
    }

    /** @return array{passed: int, already_passed: int, total: int} */
    public function import(UploadedFile $file, string $resourceClass, ?string $worksheetName = null, ?array $validationColumns = null, bool $unique = true): array
    {
        $table = $this->authorizedTable($resourceClass);

        $definitions = $this->localizedDefinitions($table);
        $rows = $this->candidateRows(app(SpreadsheetRowReader::class)->read($file, $worksheetName), $definitions, $validationColumns);
        $validateEmpty = $validationColumns !== null;

        // The visible candidate number is not globally unique. Retain every possible
        // match until all supplied identity fields have been checked.
        $index = [];
        $requestedNumbers = array_fill_keys(array_map(fn (array $row): string => $this->normalize($row['candidate_id']), $rows), true);
        foreach ($resourceClass::getEloquentQuery()->reorder('id')->lazyById(500) as $record) {
            $entry = $this->entry($record);
            $number = (string) $entry?->candidate_id;
            foreach (array_unique([$number, CandidateNumber::format($number)]) as $alias) {
                if ($this->filled($alias) && isset($requestedNumbers[$this->normalize($alias)])) {
                    $index[$this->normalize($alias)][$record->getKey()] = $record;
                }
            }
        }

        $matches = [];
        $seen = [];
        $seenValues = [];
        $errors = [];
        foreach ($rows as $rowNumber => $row) {
            $candidates = array_values($index[$this->normalize($row['candidate_id'])] ?? []);
            if ($candidates === []) {
                $errors[] = $this->error('no_match', $rowNumber);

                continue;
            }
            $matching = array_values(array_filter($candidates, fn (Model $record): bool => $this->mismatch($record, $row, $definitions, $validateEmpty) === null));
            if (count($matching) !== 1) {
                $field = count($candidates) === 1 ? $this->mismatch($candidates[0], $row, $definitions, $validateEmpty) : null;
                $errors[] = $this->error(count($matching) > 1 ? 'ambiguous' : 'mismatch', $rowNumber, [
                    'field' => $definitions[app()->getLocale()][$field]['label'] ?? $field ?? __('candidate_statistics_import.identity'),
                ]);

                continue;
            }

            $record = $matching[0];
            $entryId = $this->entry($record)?->getKey();
            if ($entryId === null || ($unique && isset($seen[$entryId]))) {
                $errors[] = $this->error($entryId === null ? 'no_match' : 'duplicate', $rowNumber);

                continue;
            }
            $seen[$entryId] = true;
            if (! $table::hasResultStatus($record, passed: true) && ! $table::canHandleResultAction($record, 'bulk_passed')) {
                $errors[] = $this->error('unauthorized', $rowNumber);

                continue;
            }
            if ($unique) {
                array_push($errors, ...$this->uniqueColumnErrors($record, $row, $definitions, $rowNumber, $seenValues));
            }
            $matches[$rowNumber] = ['id' => $record->getKey(), 'entry_id' => $entryId, 'data' => $row];
        }
        $this->failIfErrors($errors);

        return DB::transaction(function () use ($matches, $resourceClass, $table, $definitions, $validateEmpty, $validationColumns, $unique): array {
            // Lock applications first, in the same order for both menus. Recheck
            // the entire batch before progressing any workflow or sending messages.
            CustomFormEntry::query()->whereKey(array_column($matches, 'entry_id'))
                ->orderBy('id')->lockForUpdate()->get();
            $records = $resourceClass::getEloquentQuery()->whereKey(array_column($matches, 'id'))
                ->reorder('id')->lockForUpdate()->get()->keyBy('id');
            $errors = [];
            $seenValues = [];
            foreach ($matches as $rowNumber => $match) {
                $record = $records->get($match['id']);
                if ($record === null || (string) $this->entry($record)?->getKey() !== (string) $match['entry_id']
                    || $this->mismatch($record, $match['data'], $definitions, $validateEmpty) !== null) {
                    $errors[] = $this->error('changed', $rowNumber);
                } elseif (! $table::hasResultStatus($record, passed: true) && ! $table::canHandleResultAction($record, 'bulk_passed')) {
                    $errors[] = $this->error('unauthorized', $rowNumber);
                } elseif ($unique) {
                    array_push($errors, ...$this->uniqueColumnErrors($record, $match['data'], $definitions, $rowNumber, $seenValues));
                }
            }
            $this->failIfErrors($errors);

            $passed = 0;
            $alreadyPassed = 0;
            $processed = [];
            foreach ($matches as $rowNumber => $match) {
                // Every row was validated above; repeated rows must not run the
                // same candidate's workflow or notifications more than once.
                if (isset($processed[$match['entry_id']])) {
                    continue;
                }
                $processed[$match['entry_id']] = true;
                $record = $records->get($match['id']);
                if ($table::hasResultStatus($record, passed: true)) {
                    $alreadyPassed++;

                    continue;
                }
                if (! $table::markImportedPassed($record)) {
                    $this->failIfErrors([$this->error('changed', $rowNumber)]);
                }
                $passed++;
                $entryId = $match['entry_id'];
                DB::afterCommit(static function () use ($entryId): void {
                    if ($entry = CustomFormEntry::query()->find($entryId)) {
                        WorkflowStageMessages::notifyResultStage($entry);
                    }
                });
            }

            AuditLogger::log(
                action: 'imported',
                description: 'Imported passed candidates from Excel',
                metadata: ['module' => class_basename($resourceClass::getModel()), 'passed' => $passed, 'already_passed' => $alreadyPassed, 'total' => count($matches), 'validation_columns' => $validationColumns === null ? null : array_values(array_unique(['candidate_id', ...$validationColumns])), 'unique' => $unique],
            );

            return ['passed' => $passed, 'already_passed' => $alreadyPassed, 'total' => count($matches)];
        });
    }

    private function entry(Model $record): ?CustomFormEntry
    {
        return $record instanceof CustomFormEntry ? $record : $record->customFormEntry;
    }

    private function localizedDefinitions(string $table): array
    {
        $definitions = [];
        foreach (array_unique(['en', 'km', app()->getLocale()]) as $locale) {
            $definitions[$locale] = $this->withLocale($locale, fn (): array => collect($table::exportColumnDefinitions())->keyBy('field_key')->all());
            // Accept earlier downloads without restoring combined-name export columns.
            foreach (['name_khmer' => 'kh', 'name_latin' => 'en'] as $key => $language) {
                $value = fn (Model $record): string => $this->legacyName($record, $language);
                $definitions[$locale][$key] = [
                    'label' => $this->withLocale($locale, fn (): string => __('exam_results.'.$key)),
                    'value' => $value,
                    'clean' => $value,
                ];
            }
        }

        return $definitions;
    }

    private function mappedColumns(array $rows, array $definitions): array
    {
        $aliases = [];
        $ignoredHeaders = [
            $this->header('candidate_reviewed_at') => true,
            $this->header('data.candidate_reviewed_at') => true,
        ];
        foreach (array_keys($definitions) as $locale) {
            foreach (['candidate_entrance_statistics.reviewed_at', 'candidate_exit_statistics.fields.candidate_reviewed_at'] as $translation) {
                $ignoredHeaders[$this->header($this->withLocale($locale, fn (): string => __($translation)))] = true;
            }
        }
        foreach ($definitions as $columns) {
            foreach ($columns as $key => $column) {
                $aliases[$this->header($key)] = $key;
                $aliases[$this->header($column['label'])] = $key;
            }
        }
        foreach ($rows as $rowNumber => $cells) {
            if (array_filter($cells, fn ($cell): bool => $this->filled($cell)) === []) {
                continue;
            }
            $columns = [];
            $seen = [];
            foreach ($cells as $column => $label) {
                $label = trim((string) $label);
                if ($label === '') {
                    continue;
                }
                $normalizedHeader = $this->header($label);
                if (isset($ignoredHeaders[$normalizedHeader])) {
                    continue;
                }
                $key = $aliases[$normalizedHeader] ?? null;
                if ($key !== null && isset($seen[$key])) {
                    $this->failIfErrors([__('candidate_statistics_import.errors.duplicate_headers')]);
                }
                if ($key !== null) {
                    $seen[$key] = true;
                }
                $metadata = in_array($key, ['row_number', 'candidate_status'], true);
                $help = match (true) {
                    $key === null => __('candidate_statistics_import.validation_unsupported_help'),
                    $key === 'row_number' => __('candidate_statistics_import.validation_row_number_help'),
                    $metadata => __('candidate_statistics_import.validation_metadata_help'),
                    default => null,
                };
                $columns[] = [
                    'key' => $key ?? 'unknown_'.$column,
                    'label' => $definitions[app()->getLocale()][$key]['label'] ?? $label,
                    'header' => $label,
                    'column' => $column,
                    'supported' => $key !== null,
                    'locked' => $key === 'candidate_id',
                    'default' => $key !== null && ! $metadata,
                    'help' => $help,
                ];
            }

            return ['row' => $rowNumber, 'columns' => $columns];
        }

        $this->failIfErrors([__('candidate_statistics_import.errors.no_rows')]);

        return [];
    }

    private function candidateRows(array $rows, array $definitions, ?array $validationColumns = null): array
    {
        $parsed = $this->mappedColumns($rows, $definitions);
        $headers = [];
        foreach ($parsed['columns'] as $column) {
            if ($column['supported']) {
                $headers[$column['key']] = $column['column'];
            }
        }
        if (! isset($headers['candidate_id'])) {
            $this->failIfErrors([__('candidate_statistics_import.errors.only_id_headers')]);
        }
        if ($validationColumns === null) {
            $hasNameHeaders = isset($headers['name_khmer']) || isset($headers['name_latin'])
                || isset($headers['first_name_kh'], $headers['last_name_kh'])
                || isset($headers['first_name_en'], $headers['last_name_en']);
            if (! isset($headers['date_of_birth']) || ! $hasNameHeaders) {
                $this->failIfErrors([__('candidate_statistics_import.errors.headers')]);
            }
            $fields = self::IDENTITY_FIELDS;
        } else {
            foreach ($validationColumns as $key) {
                if (! is_string($key) || ! array_key_exists($key, $headers)) {
                    $this->failIfErrors([__('candidate_statistics_import.errors.invalid_selection')]);
                }
            }
            $fields = array_values(array_unique(['candidate_id', ...$validationColumns]));
        }

        $candidates = [];
        $errors = [];
        foreach ($rows as $rowNumber => $cells) {
            if ($rowNumber <= $parsed['row'] || array_filter($cells, fn ($cell): bool => $this->filled($cell)) === []) {

                continue;
            }

            $row = [];
            foreach ($fields as $key) {
                if (isset($headers[$key])) {
                    $row[$key] = trim((string) ($cells[$headers[$key]] ?? ''));
                }
            }
            $missingLegacyIdentity = $validationColumns === null
                && (! $this->filled($row['date_of_birth'] ?? '') || ! $this->hasNameIdentity($row));
            if (! $this->filled($row['candidate_id'] ?? '') && $validationColumns !== null) {
                $errors[] = $this->error('missing_candidate_id', $rowNumber);
            } elseif (! $this->filled($row['candidate_id'] ?? '') || $missingLegacyIdentity) {
                $errors[] = $this->error('missing_identity', $rowNumber);
            } elseif ($this->filled($row['date_of_birth'] ?? '') && $this->date($row['date_of_birth']) === null) {
                $errors[] = $this->error('invalid_date', $rowNumber);
            } elseif (isset($row['row_number']) && preg_match('/^[1-9][0-9]*$/D', $this->normalize($row['row_number'])) !== 1) {
                $errors[] = $this->error('row_number', $rowNumber);
            } else {
                $candidates[$rowNumber] = $row;
            }
        }
        $this->failIfErrors($errors);
        if ($candidates === []) {
            $this->failIfErrors([__('candidate_statistics_import.errors.no_rows')]);
        }

        return $candidates;
    }

    /** Return the first supplied identity field that disagrees with the export. */
    private function mismatch(Model $record, array $row, array $definitions, bool $validateEmpty = false): ?string
    {
        foreach ($row as $key => $value) {
            if ($key === 'row_number') {
                continue;
            }
            $splitName = in_array($key, ['first_name_kh', 'last_name_kh', 'first_name_en', 'last_name_en'], true);
            $language = str_ends_with($key, '_kh') ? 'kh' : 'en';
            $hasSplitIdentity = isset($row['first_name_'.$language], $row['last_name_'.$language])
                && ($this->filled($row['first_name_'.$language]) || $this->filled($row['last_name_'.$language]));
            if (! $validateEmpty && ! $this->filled($value) && ! ($splitName && $hasSplitIdentity)) {
                continue;
            }
            $aliases = [];
            foreach ($definitions as $locale => $columns) {
                $aliases = [...$aliases, ...$this->withLocale($locale, fn (): array => [
                    (string) $columns[$key]['value']($record, 0),
                    (string) $columns[$key]['clean']($record, 0),
                ])];
            }
            if ($key === 'candidate_id') {
                $aliases[] = (string) $this->entry($record)?->candidate_id;
            }
            $normalize = match (true) {
                $key === 'date_of_birth' => $this->date(...),
                $splitName || $validateEmpty => fn (string $part): string => $this->filled($part) ? $this->normalize($part) : '',
                default => $this->normalize(...),
            };
            if (! in_array($normalize($value), array_map($normalize, $aliases), true)) {
                return $key;
            }
        }

        return null;
    }

    /** Check each enabled column independently, within this upload only. */
    private function uniqueColumnErrors(Model $record, array $row, array $definitions, int $rowNumber, array &$seenValues): array
    {
        $errors = [];
        foreach ($row as $key => $value) {
            if (! $this->filled($value)) {
                continue;
            }

            $source = $key === 'date_of_birth' ? $this->date($value) : $this->normalize($value);
            if (! $this->filled($source)) {
                continue;
            }
            $tokens = ['source:'.$source];
            if (! in_array($key, ['candidate_id', 'row_number', 'date_of_birth'], true)) {
                // Compare both uploaded text and its matched raw value so translated
                // labels cannot disguise duplicates, including repeated display labels.
                $canonical = $this->withLocale('en', function () use ($record, $key, $definitions): string {
                    $column = $definitions['en'][$key];
                    $value = (string) $column['clean']($record, 0);

                    return $this->filled($value) ? $value : (string) $column['value']($record, 0);
                });
                $canonical = $this->normalize($canonical);
                if ($this->filled($canonical)) {
                    $tokens[] = 'canonical:'.$canonical;
                }
            }

            foreach ($tokens as $token) {
                if (isset($seenValues[$key][$token])) {
                    $errors[] = $this->error('duplicate_field', $rowNumber, [
                        'field' => $definitions[app()->getLocale()][$key]['label'] ?? $key,
                        'first_row' => $seenValues[$key][$token],
                    ]);

                    break;
                }
            }
            foreach ($tokens as $token) {
                $seenValues[$key][$token] ??= $rowNumber;
            }
        }

        return $errors;
    }

    private function hasNameIdentity(array $row): bool
    {
        if ($this->filled($row['name_khmer'] ?? '') || $this->filled($row['name_latin'] ?? '')) {
            return true;
        }
        foreach (['kh', 'en'] as $language) {
            if (isset($row['first_name_'.$language], $row['last_name_'.$language])
                && ($this->filled($row['first_name_'.$language]) || $this->filled($row['last_name_'.$language]))) {
                return true;
            }
        }

        return false;
    }

    private function legacyName(Model $record, string $language): string
    {
        $data = $record instanceof CustomFormEntry ? $record->data : $record->getAttributes();
        $name = trim(implode(' ', array_filter([
            data_get($data, 'first_name_'.$language), data_get($data, 'last_name_'.$language),
        ], fn ($value): bool => $this->filled($value))));
        if ($name !== '' || ! $record instanceof CustomFormEntry) {
            return $name;
        }

        return (string) (data_get($data, $language === 'kh' ? 'name_khmer' : 'name_latin')
            ?: ($language === 'kh' ? $record->creator?->name : $record->creator?->name_latin));
    }

    private function header(string $value): string
    {
        return preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '', $this->normalize($value)) ?? '';
    }

    private function normalize(string $value): string
    {
        $value = strtr($value, array_combine(preg_split('//u', '០១២៣៤៥៦៧៨៩', -1, PREG_SPLIT_NO_EMPTY), range(0, 9)));
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $value) ?? $value;

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }

    private function date(string $value): ?string
    {
        $value = strtr($this->normalize($value), array_combine(
            ['មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា', 'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ'],
            ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'],
        ));
        foreach (['Y-m-d', 'd-M-Y', 'd-F-Y', 'd/m/Y', 'd-m-Y', 'Y-m-d H:i:s', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.vP'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function filled(mixed $value): bool
    {
        return ! in_array(trim((string) $value), ['', '-', '—'], true);
    }

    private function error(string $key, int $rowNumber, array $replace = []): string
    {
        return __('candidate_statistics_import.errors.'.$key, ['row' => $rowNumber, ...$replace]);
    }

    private function failIfErrors(array $errors): void
    {
        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => array_slice($errors, 0, 20)]);
        }
    }
}
