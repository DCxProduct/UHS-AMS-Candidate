<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Actions\Action; // Use generic Action
use Illuminate\Database\Eloquent\Builder;

class CustomFormEntriesTable
{
    public static function configure(Table $table): Table
    {
        $formId = self::getFormId($table);

        return $table
            ->columns(self::getColumns($formId))
            ->filters(self::getFilters($formId))
            ->recordActions(self::getRecordActions())
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn(Builder $query) => self::applyQueryConstraints($query, $formId));
    }

    protected static function getFormId(Table $table): ?string
    {
        $livewire = $table->getLivewire();

        return data_get($livewire, 'tableFilters.custom_form_id.value')
            ?? data_get($livewire, 'activeFormId')
            ?? request()->input('tableFilters.custom_form_id.value')
            ?? data_get(request()->query('tableFilters'), 'custom_form_id.value')
            ?? request()->query('form_id');
    }

    protected static function getColumns(?string $formId): array
    {
        $columns = [];

        // Fetch fields metadata from parent CustomFormField table first
        $fieldsMetadata = \Chanthoeun\FilamentCustomForms\Models\CustomFormField::query()
            ->when($formId, fn($query) => $query->where('custom_form_id', $formId))
            ->orderBy('sort')
            ->get()
            ->keyBy('name');

        $definedKeys = $fieldsMetadata->keys();

        // Fetch keys from existing entries to catch any legacy/extra data (Limited to 20 for performance)
        $dataKeys = \Chanthoeun\FilamentCustomForms\Models\CustomFormEntry::query()
            ->when($formId, fn($query) => $query->where('custom_form_id', $formId))
            ->latest()
            ->limit(20)
            ->get()
            ->flatMap(fn($entry) => array_keys(is_array($entry->data) ? $entry->data : []))
            ->unique();

        $keys = $definedKeys->merge($dataKeys)->unique();

        $sortOrder = $fieldsMetadata->pluck('sort', 'name');
        $fieldTypes = $fieldsMetadata->pluck('type', 'name');
        $fieldOptions = $fieldsMetadata->pluck('options', 'name');
        $fieldsById = $fieldsMetadata->keyBy('id');

        $sortedKeys = $keys->sortBy(fn($key) => $sortOrder[$key] ?? 999999);

        foreach ($sortedKeys as $key) {
            if (in_array(($fieldTypes[$key] ?? null), ['repeater', 'section', 'grid', 'fieldset'])) {
                continue;
            }

            $field = $fieldsMetadata[$key] ?? null;
            if ($field && $field->parent_id) {
                $parent = $fieldsById[$field->parent_id] ?? null;
                if ($parent && $parent->type === 'repeater') {
                    continue;
                }
            }

            $columnKey = "data.{$key}";
            $label = \Illuminate\Support\Str::headline($key);

            $column = TextColumn::make($columnKey)
                ->label($label)
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: false);

            if (($fieldTypes[$key] ?? null) === 'number_input') {
                $column->numeric();
            }

            if (($fieldTypes[$key] ?? null) === 'money') {
                $currency = $fieldOptions[$key]['currency'] ?? 'USD';
                $column->money(strtoupper($currency));
            }

            if (($fieldTypes[$key] ?? null) === 'time_picker') {
                $column->time();
            }

            if (($fieldTypes[$key] ?? null) === 'time_picker') {
                $column->time();
            }

            $columns[] = $column;
        }

        if (count($columns) > 0) {
            $columns[] = TextColumn::make('created_at')
                ->label(__('filament-custom-forms::fcf.general.created_at'))
                ->dateTime()
                ->sortable();
        }

        return $columns;
    }

    protected static function getFilters(?string $formId): array
    {
        $filters = [];

        if ($formId) {
            $formSchema = \Chanthoeun\FilamentCustomForms\Models\CustomForm::find($formId);
            if ($formSchema) {
                $schemaFields = $formSchema->fields()->orderBy('sort')->get();

                foreach ($schemaFields as $field) {
                    $jsonKey = $field->name;
                    $label = $field->label ?? $field->name;

                    switch ($field->type) {
                        case 'boolean':
                            $filters[] = TernaryFilter::make($field->name)
                                ->label($label)
                                ->query(
                                    fn(Builder $query, array $data) =>
                                    $query->when(
                                        isset($data['value']),
                                        fn($q) => $q->where("data->{$jsonKey}", $data['value'] === '1' || $data['value'] === true)
                                    )
                                );
                            break;

                        case 'select':
                            $choices = $field->options['choices'] ?? [];
                            if (!empty($choices)) {
                                $filters[] = SelectFilter::make($field->name)
                                    ->label($label)
                                    ->options($choices)
                                    ->query(
                                        fn(Builder $query, array $data) =>
                                        $query->when(
                                            $data['value'],
                                            fn($q) => $q->where("data->{$jsonKey}", $data['value'])
                                        )
                                    );
                            }
                            break;

                        case 'date_picker':
                            $filters[] = Filter::make($field->name)
                                ->label($label)
                                ->form([
                                    DatePicker::make('from')->label($label . ' From'),
                                    DatePicker::make('until')->label($label . ' Until'),
                                ])
                                ->query(function (Builder $query, array $data) use ($jsonKey) {
                                    return $query
                                        ->when($data['from'], fn($q) => $q->where("data->{$jsonKey}", '>=', $data['from']))
                                        ->when($data['until'], fn($q) => $q->where("data->{$jsonKey}", '<=', $data['until']));
                                });
                            break;
                    }
                }
            }
        }

        $filters[] = SelectFilter::make('custom_form_id')
            ->label(__('filament-custom-forms::fcf.form.single'))
            ->options(\Chanthoeun\FilamentCustomForms\Models\CustomForm::pluck('name', 'id'))
            ->hidden();

        return $filters;
    }

    protected static function getRecordActions(): array
    {
        $actions = [
            EditAction::make(),
            DeleteAction::make(),
        ];

        if (class_exists(\Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::class)) {
            $actions[] = \Chanthoeun\FilamentDocumentBuilder\Tables\Actions\DownloadPdfAction::make('download_pdf')
                ->templateType(fn ($record) => 'custom_form_' . $record->custom_form_id)
                ->filename(fn ($record) => 'document-' . $record->id . '.pdf');
        }

        return $actions;
    }

    protected static function applyQueryConstraints(Builder $query, ?string $formId): Builder
    {
        return $query->with(['creator', 'customForm'])
            ->when($formId, fn($q, $id) => $q->where('custom_form_id', $id));
    }
}
