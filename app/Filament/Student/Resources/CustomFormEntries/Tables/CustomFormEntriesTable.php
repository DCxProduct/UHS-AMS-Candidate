<?php

namespace App\Filament\Student\Resources\CustomFormEntries\Tables;

use App\Filament\Student\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CustomFormEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ...self::dynamicColumns(),

                TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->dateTime('M j, Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-x-mark')
            ->emptyStateHeading(__('app.no_entries', [
                'name' => CustomFormEntryResource::currentFormName(),
            ]))
            ->recordUrl(null);
    }

    protected static function dynamicColumns(): array
    {
        $formId = CustomFormEntryResource::currentFormId();

        if (! $formId || ! Schema::hasTable('custom_form_fields')) {
            return [
                TextColumn::make('empty_name')
                    ->label(__('app.name'))
                    ->state('-'),
            ];
        }

        $columns = Schema::getColumnListing('custom_form_fields');

        $formColumn = self::firstExistingColumn($columns, [
            'custom_form_id',
            'form_id',
        ]);

        if (! $formColumn) {
            return [
                TextColumn::make('empty_name')
                    ->label(__('app.name'))
                    ->state('-'),
            ];
        }

        $sortColumn = self::firstExistingColumn($columns, [
            'display_order',
            'sort',
            'sort_order',
            'order_column',
            'ordering',
            'position',
        ]);

        $query = DB::table('custom_form_fields')
            ->where($formColumn, $formId);

        if (in_array('is_active', $columns, true)) {
            $query->where('is_active', true);
        }

        if (in_array('active', $columns, true)) {
            $query->where('active', true);
        }

        if ($sortColumn) {
            $query->orderBy($sortColumn);
        } else {
            $query->orderBy('id');
        }

        $dynamicColumns = $query
            ->get()
            ->reject(fn ($field): bool => self::isSectionField($field))
            ->reject(fn ($field): bool => self::isHiddenInTable($field))
            ->take(8)
            ->map(function ($field): TextColumn {
                $name = self::fieldName($field);

                return TextColumn::make('data_' . $name)
                    ->label(self::fieldLabel($field))
                    ->state(fn (CustomFormEntry $record): string => self::displayValue($record, $name))
                    ->searchable(false)
                    ->wrap();
            })
            ->values()
            ->all();

        if (count($dynamicColumns) === 0) {
            return [
                TextColumn::make('empty_name')
                    ->label(__('app.name'))
                    ->state('-'),
            ];
        }

        return $dynamicColumns;
    }

    protected static function displayValue(CustomFormEntry $record, string $fieldName): string
    {
        $data = $record->data ?? [];

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($data)) {
            return '-';
        }

        $possibleKeys = [
            $fieldName,
            str($fieldName)->snake()->toString(),
            str($fieldName)->camel()->toString(),
            str($fieldName)->title()->replace(' ', '_')->toString(),
            str($fieldName)->title()->replace('_', ' ')->toString(),
            ucfirst($fieldName),
            strtoupper($fieldName),
        ];

        foreach ($possibleKeys as $key) {
            $value = data_get($data, $key);

            if (filled($value)) {
                if (is_bool($value)) {
                    return $value ? __('app.yes') : __('app.no');
                }

                if (is_array($value)) {
                    return collect($value)
                        ->filter(fn ($item): bool => filled($item))
                        ->map(fn ($item): string => is_scalar($item)
                            ? (string) $item
                            : json_encode($item, JSON_UNESCAPED_UNICODE)
                        )
                        ->implode(', ');
                }

                return (string) $value;
            }
        }

        return '-';
    }

    protected static function fieldName(object $field): string
    {
        $name = (string) (
            $field->name
            ?? $field->field_name
            ?? $field->key
            ?? $field->slug
            ?? ''
        );

        return Str::of($name)
            ->trim()
            ->replace(' ', '_')
            ->lower()
            ->toString();
    }

    protected static function fieldLabel(object $field): string
    {
        $name = self::fieldName($field);

        if (filled($name)) {
            foreach ([
                         'app.form_fields.' . $name,
                         'app.fields.' . $name,
                     ] as $key) {
                $translated = __($key);

                if ($translated !== $key) {
                    return (string) $translated;
                }
            }
        }

        return (string) (
            $field->label
            ?? $field->name
            ?? $field->field_name
            ?? __('app.untitled_form')
        );
    }

    protected static function isSectionField(object $field): bool
    {
        $type = Str::of((string) ($field->type ?? $field->field_type ?? ''))
            ->lower()
            ->replace('-', '_')
            ->snake()
            ->toString();

        return in_array($type, [
            'step',
            'wizard_step',
            'section',
            'fieldset',
            'container',
            'group',
            'heading',
            'card',
            'grid',

            // Display-only fields should not show as table columns.
            'info',
            'placeholder',
            'html',
            'markdown',
            'view',
            'hidden',
        ], true);
    }

    protected static function isHiddenInTable(object $field): bool
    {
        $config = self::fieldConfig($field);

        return (bool) (
            $field->hide_in_table
            ?? $field->hide_in_view
            ?? $config['hide_in_table']
            ?? $config['hide_in_view']
            ?? false
        );
    }

    protected static function fieldConfig(object $field): array
    {
        $raw = $field->configuration
            ?? $field->config
            ?? $field->settings
            ?? [];

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && filled($raw)) {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    protected static function firstExistingColumn(array $columns, array $names): ?string
    {
        foreach ($names as $name) {
            if (in_array($name, $columns, true)) {
                return $name;
            }
        }

        return null;
    }
}
