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
use Illuminate\Database\Eloquent\Builder;

class CustomFormEntriesTable
{
    public static function configure(Table $table): Table
    {
        $formId = self::getFormId($table);

        return $table
            ->columns(self::getColumns($formId))
            ->filters([])
            ->recordActions(self::getRecordActions())
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => self::currentPanelIsAdmin()),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => self::applyQueryConstraints($query, $formId));
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
        if ($formId && self::isNationalExaminationForm($formId)) {
            return self::getNationalExaminationColumns();
        }

        if ($formId && self::isProfileForm($formId)) {
            return self::getProfileColumns();
        }

        $columns = [];

        $fieldsMetadata = \Chanthoeun\FilamentCustomForms\Models\CustomFormField::query()
            ->when($formId, fn ($query) => $query->where('custom_form_id', $formId))
            ->orderBy('sort')
            ->get()
            ->keyBy('name');

        $definedKeys = $fieldsMetadata->keys();

        $dataKeys = \Chanthoeun\FilamentCustomForms\Models\CustomFormEntry::query()
            ->when($formId, fn ($query) => $query->where('custom_form_id', $formId))
            ->latest()
            ->limit(20)
            ->get()
            ->flatMap(fn ($entry) => array_keys(is_array($entry->data) ? $entry->data : []))
            ->unique();

        $keys = $definedKeys->merge($dataKeys)->unique();

        $sortOrder = $fieldsMetadata->pluck('sort', 'name');
        $fieldTypes = $fieldsMetadata->pluck('type', 'name');
        $fieldOptions = $fieldsMetadata->pluck('options', 'name');
        $fieldsById = $fieldsMetadata->keyBy('id');

        $sortedKeys = $keys->sortBy(fn ($key) => $sortOrder[$key] ?? 999999);

        foreach ($sortedKeys as $key) {
            if (in_array(($fieldTypes[$key] ?? null), ['repeater', 'section', 'grid', 'fieldset'], true)) {
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
                ->label($label);

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

    protected static function isNationalExaminationForm(string $formId): bool
    {
        return \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
            ->whereKey($formId)
            ->where('slug', 'national-examination-registration')
            ->exists();
    }

    protected static function getNationalExaminationColumns(): array
    {
        return [
            TextColumn::make('data.student_id')
                ->label('Student ID')
                ->placeholder('-')
                ->wrap(),

            TextColumn::make('data.national_registration_number')
                ->label('National Registration Number')
                ->placeholder('-')
                ->wrap(),

            TextColumn::make('data.first_name_kh')
                ->label('First Name (Khmer)')
                ->placeholder('-')
                ->wrap(),

            TextColumn::make('data.last_name_kh')
                ->label('Last Name (Khmer)')
                ->placeholder('-')
                ->wrap(),

            TextColumn::make('data.registration_status')
                ->label('Registration Status')
                ->placeholder('-')
                ->badge(),

            TextColumn::make('data.registration_date')
                ->label('Registration Date')
                ->formatStateUsing(
                    fn (mixed $state): string => self::formatProfileDate($state)
                )
                ->placeholder('-'),
        ];
    }

    protected static function isProfileForm(string $formId): bool
    {
        return \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
            ->whereKey($formId)
            ->where('slug', 'profile')
            ->exists();
    }

    protected static function getProfileColumns(): array
    {
        return [
            TextColumn::make('data.first_name_kh')
                ->label('First Name (Khmer)')
                ->placeholder('-'),

            TextColumn::make('data.last_name_kh')
                ->label('Last Name (Khmer)')
                ->placeholder('-'),

            TextColumn::make('data.date_of_birth')
                ->label('Date of Birth')
                ->formatStateUsing(
                    fn (mixed $state): string => self::formatProfileDate($state)
                )
                ->placeholder('-'),

            TextColumn::make('data.exam_period')
                ->label('Exam Date')
                ->formatStateUsing(
                    fn (mixed $state): string => self::formatProfileDate($state)
                )
                ->placeholder('-'),

            TextColumn::make('data.exam_center')
                ->label('Exam Center')
                ->placeholder('-'),

            TextColumn::make('data.current_occupation')
                ->label('Current Occupation')
                ->placeholder('-'),

            TextColumn::make('data.place_of_work')
                ->label('Place of Work / Organization')
                ->placeholder('-')
                ->wrap(),
        ];
    }

    protected static function formatProfileDate(mixed $state): string
    {
        if (blank($state)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($state)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $state;
        }
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
                                    fn (Builder $query, array $data) =>
                                    $query->when(
                                        isset($data['value']),
                                        fn ($q) => $q->where("data->{$jsonKey}", $data['value'] === '1' || $data['value'] === true)
                                    )
                                );
                            break;

                        case 'select':
                        case 'select_dropdown':
                            $choices = $field->options['choices'] ?? [];

                            if (! empty($choices)) {
                                $filters[] = SelectFilter::make($field->name)
                                    ->label($label)
                                    ->options($choices)
                                    ->query(
                                        fn (Builder $query, array $data) =>
                                        $query->when(
                                            $data['value'] ?? null,
                                            fn ($q) => $q->where("data->{$jsonKey}", $data['value'])
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
                                        ->when($data['from'] ?? null, fn ($q) => $q->where("data->{$jsonKey}", '>=', $data['from']))
                                        ->when($data['until'] ?? null, fn ($q) => $q->where("data->{$jsonKey}", '<=', $data['until']));
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
            EditAction::make()
                ->visible(fn ($record): bool => self::canEditOrDelete($record)),

            DeleteAction::make()
                ->visible(fn ($record): bool => self::canEditOrDelete($record)),
        ];

        if (class_exists(\Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::class)) {
            $actions[] = \Chanthoeun\FilamentDocumentBuilder\Tables\Actions\DownloadPdfAction::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->templateType(fn ($record) => 'custom_form_' . $record->custom_form_id)
                ->filename(fn ($record) => 'document-' . $record->id . '.pdf')
                ->visible(fn ($record): bool => self::canDownloadPdf($record));
        }

        return $actions;
    }

    protected static function canEditOrDelete($record): bool
    {
        $status = strtolower((string) ($record->review_status ?? 'pending'));

        return in_array($status, [
            '',
            'pending',
        ], true);
    }

    protected static function canDownloadPdf($record): bool
    {
        $status = strtolower((string) ($record->review_status ?? 'pending'));

        return in_array($status, [
            'passed',
            'accepted',
            'approved',
        ], true);
    }

    protected static function currentPanelIsAdmin(): bool
    {
        return \Filament\Facades\Filament::getCurrentPanel()
            && \Filament\Facades\Filament::getCurrentPanel()->getId() === 'admin';
    }

    protected static function applyQueryConstraints(Builder $query, ?string $formId): Builder
    {
        $query
            ->with(['creator', 'customForm'])
            ->when($formId, fn ($q, $id) => $q->where('custom_form_id', $id));

        if (
            \Filament\Facades\Filament::getCurrentPanel()
            && \Filament\Facades\Filament::getCurrentPanel()->getId() === 'student'
        ) {
            $userId = auth()->id();

            if (! $userId) {
                return $query->whereRaw('1 = 0');
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('custom_form_entries', 'created_by')) {
                return $query->where('created_by', $userId);
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('custom_form_entries', 'user_id')) {
                return $query->where('user_id', $userId);
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('custom_form_entries', 'created_by_id')) {
                return $query->where('created_by_id', $userId);
            }

            return $query->whereRaw('1 = 0');
        }

        return $query;
    }
}
