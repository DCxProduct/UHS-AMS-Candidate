<?php

namespace App\Support;

use App\Services\CandidateStatisticsImportService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class CandidateStatisticsImportForm
{
    public static function schema(string $resourceClass): array
    {
        return [
            FileUpload::make('file')
                ->label(__('candidate_statistics_import.file_label'))
                ->required()
                ->storeFiles(false)
                ->acceptedFileTypes([
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/csv',
                    'text/plain',
                    'application/csv',
                    'application/vnd.ms-excel',
                ])
                ->rules(['extensions:xlsx,csv'])
                ->maxSize(10240)
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set, Component $component) => self::refreshColumns($get, $set, $component, $resourceClass)),
            Toggle::make('unique')
                ->label(__('candidate_statistics_import.unique'))
                ->default(true)
                ->visible(fn (Get $get): bool => filled($get('detected_columns'))),
            Section::make(fn (Get $get): string => __('candidate_statistics_import.validation_title', ['count' => count($get('detected_columns') ?? [])]))
                ->key(fn (Get $get): string => 'import-validation-columns-'.sha1(json_encode($get('detected_columns') ?? [])))
                ->visible(fn (Get $get): bool => filled($get('detected_columns')))
                ->columns(['default' => 1, 'md' => 2])
                ->schema(fn (Get $get): array => collect($get('detected_columns') ?? [])
                    ->filter(fn ($column): bool => is_array($column) && isset($column['key'], $column['label']))
                    ->map(fn (array $column): Toggle => Toggle::make('validation_columns.'.$column['key'])
                        ->label($column['label'])
                        ->helperText($column['help'] ?? null)
                        ->default((bool) ($column['default'] ?? false))
                        ->disabled(! ($column['supported'] ?? false) || ($column['locked'] ?? false))
                        ->dehydrated((bool) ($column['supported'] ?? false))
                        ->live())
                    ->all()),
        ];
    }

    private static function refreshColumns(Get $get, Set $set, Component $component, string $resourceClass): void
    {
        $set('detected_columns', []);
        $set('validation_columns', []);
        $set('unique', true);

        $livewire = $component->getLivewire();
        $statePath = $component->getStatePath();
        $filePath = substr($statePath, 0, strrpos($statePath, '.')).'.file';
        $livewire->resetValidation($filePath);
        $file = self::uploadedFile($get('file'));
        if ($file === null) {
            return;
        }

        try {
            $columns = app(CandidateStatisticsImportService::class)->previewColumns(
                $file,
                $resourceClass,
                SpreadsheetRowReader::AUTO_WORKSHEET,
            );
            $set('detected_columns', $columns);
            $set('validation_columns', collect($columns)
                ->mapWithKeys(fn (array $column): array => [$column['key'] => (bool) $column['default']])
                ->all());
        } catch (ValidationException $exception) {
            foreach ($exception->errors()['file'] ?? [] as $message) {
                $livewire->addError($filePath, $message);
            }
        }
    }

    private static function uploadedFile(mixed $state): ?UploadedFile
    {
        if ($state instanceof UploadedFile) {
            return $state;
        }

        foreach (is_array($state) ? $state : [] as $file) {
            if ($file instanceof UploadedFile) {
                return $file;
            }
        }

        return null;
    }
}
