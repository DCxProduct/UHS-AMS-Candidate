<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Tables;

use App\Models\User;
use App\Models\GeoLocation;
use App\Support\NotificationLanguage;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class CustomFormEntriesTable
{
    public static function configure(Table $table): Table
    {
        $formId = self::getFormId($table);

        return $table
            ->recordAction(null)
            ->recordUrl(null)
            ->columns(self::getColumns($formId))
            ->filters(self::getFilters($formId), layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns(4)
            ->defaultSort('created_at', 'desc')
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
        if ($formId && self::isProfileForm($formId)) {
            return self::getProfileColumns();
        }

        $nationalExamFormId = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
            ->where('slug', 'national-examination-registration')
            ->value('id');

        $targetFormIds = [];
        if (empty($formId) || (string)$formId === (string)$nationalExamFormId) {
            if ($nationalExamFormId) {
                $targetFormIds[] = $nationalExamFormId;
                $childFormIds = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
                    ->where('custom_form_id', $nationalExamFormId)
                    ->where('menu_placement', 'sub_item')
                    ->whereNotNull('sub_item_type')
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();
                $targetFormIds = array_merge($targetFormIds, $childFormIds);
            }
        } elseif ($formId) {
            $targetFormIds[] = $formId;

            $childFormIds = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
                ->where('custom_form_id', $formId)
                ->where('menu_placement', 'sub_item')
                ->whereNotNull('sub_item_type')
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            $targetFormIds = array_merge($targetFormIds, $childFormIds);
        }

        $additionalColumns = [];
        if (!empty($targetFormIds)) {
            $fields = \Chanthoeun\FilamentCustomForms\Models\CustomFormField::query()
                ->whereIn('custom_form_id', $targetFormIds)
                ->whereNotIn('type', ['section', 'grid', 'fieldset', 'repeater', 'wizard', 'info'])
                ->orderBy('sort')
                ->get();

            $processedKeys = [
                'form_selection',
                'list_number',
                'selected_major',
                'degree_level_major',
                'gender',
                'phone_number',
                'academic_year',
                'class',
                'registration_status',
                'candidate_status',
            ];

            foreach ($fields as $field) {
                $key = (string) $field->name;
                if (blank($key) || in_array($key, $processedKeys, true)) {
                    continue;
                }

                if (isset($additionalColumns[$key])) {
                    continue;
                }

                $label = self::transText($field->label ?: $key);
                if ($key === 'last_name_kh') {
                    $label = app()->getLocale() === 'km' ? 'នាមត្រកូល (ខ្មែរ)' : 'Family Name (Khmer)';
                } elseif ($key === 'first_name_kh') {
                    $label = app()->getLocale() === 'km' ? 'នាមខ្លួន (ខ្មែរ)' : 'Given Name (Khmer)';
                } elseif ($key === 'last_name_en') {
                    $label = app()->getLocale() === 'km' ? 'នាមត្រកូល (អង់គ្លេស)' : 'Family Name (English)';
                } elseif ($key === 'first_name_en') {
                    $label = app()->getLocale() === 'km' ? 'នាមខ្លួន (អង់គ្លេស)' : 'Given Name (English)';
                }

                $column = TextColumn::make("data.{$key}")
                    ->label($label)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap();

                if (self::isGeoColumn((string) $key)) {
                    $column->formatStateUsing(fn (mixed $state): string => self::geoLocationName($state));
                }

                // Format Choice Columns (Select, Dropdown, Radio, Checkbox, etc.)
                if (!self::isGeoColumn((string) $key)) {
                    $fieldOptions = is_array($field->options) ? $field->options : json_decode((string) $field->options, true);
                    $choices = $fieldOptions['choices'] ?? null;
                    if (is_array($choices) && !empty($choices)) {
                        $column->formatStateUsing(function (mixed $state) use ($choices): string {
                            if (blank($state)) {
                                return '-';
                            }

                            $transChoices = collect($choices)
                                ->mapWithKeys(function ($label, $key): array {
                                    if (is_array($label) && array_key_exists('value', $label)) {
                                        return [
                                            (string) $label['value'] => self::transText($label['label'] ?? $label['value']),
                                        ];
                                    }
                                    return [
                                        (string) $key => self::transText($label),
                                    ];
                                })
                                ->toArray();

                            if (is_array($state)) {
                                return collect($state)
                                    ->map(fn ($val) => $transChoices[(string)$val] ?? (string)$val)
                                    ->join(', ');
                            }

                            if (is_string($state) && str_starts_with(trim($state), '[')) {
                                $decoded = json_decode($state, true);
                                if (is_array($decoded)) {
                                    return collect($decoded)
                                        ->map(fn ($val) => $transChoices[(string)$val] ?? (string)$val)
                                        ->join(', ');
                                }
                            }

                            return $transChoices[(string)$state] ?? (string)$state;
                        });
                    }
                }

                // Format Date Columns
                if (in_array($field->type, ['date_picker', 'date_time_picker'], true)) {
                    $column->formatStateUsing(function (mixed $state): string {
                        if (blank($state)) {
                            return '-';
                        }
                        try {
                            return \Carbon\Carbon::parse($state)->format('d-M-Y');
                        } catch (\Throwable) {
                            return (string) $state;
                        }
                    });
                }

                $additionalColumns[$key] = $column;
            }

            // Also scan recent entry data keys for any extra/ad-hoc data
            $dataKeys = \Chanthoeun\FilamentCustomForms\Models\CustomFormEntry::query()
                ->whereIn('custom_form_id', $targetFormIds)
                ->latest()
                ->limit(20)
                ->get()
                ->flatMap(fn ($entry) => array_keys(is_array($entry->data) ? $entry->data : []))
                ->unique();

            foreach ($dataKeys as $key) {
                if (blank($key) || in_array($key, $processedKeys, true) || isset($additionalColumns[$key])) {
                    continue;
                }

                $label = \Illuminate\Support\Str::headline($key);
                if ($key === 'last_name_kh') {
                    $label = app()->getLocale() === 'km' ? 'នាមត្រកូល (ខ្មែរ)' : 'Family Name (Khmer)';
                } elseif ($key === 'first_name_kh') {
                    $label = app()->getLocale() === 'km' ? 'នាមខ្លួន (ខ្មែរ)' : 'Given Name (Khmer)';
                } elseif ($key === 'last_name_en') {
                    $label = app()->getLocale() === 'km' ? 'នាមត្រកូល (អង់គ្លេស)' : 'Family Name (English)';
                } elseif ($key === 'first_name_en') {
                    $label = app()->getLocale() === 'km' ? 'នាមខ្លួន (អង់គ្លេស)' : 'Given Name (English)';
                }

                $column = TextColumn::make("data.{$key}")
                    ->label($label)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap();

                // Format if it looks like a date/time string
                if (str_contains($key, 'date') || str_contains($key, 'period') || str_contains($key, 'dob') || str_contains($key, 'born')) {
                    $column->formatStateUsing(function (mixed $state): string {
                        if (blank($state)) {
                            return '-';
                        }
                        try {
                            return \Carbon\Carbon::parse($state)->format('d-M-Y');
                        } catch (\Throwable) {
                            return (string) $state;
                        }
                    });
                }

                $additionalColumns[$key] = $column;
            }
        }

        $columns = [
            // Row number
            TextColumn::make('row_number')
                ->label(app()->getLocale() === 'km' ? 'ល.រ' : 'No.')
                ->rowIndex()
                ->alignCenter()
                ->width('60px'),

            // 1. Form Type
            TextColumn::make('data.form_selection')
                ->label(app()->getLocale() === 'km' ? 'ប្រភេទទម្រង់' : 'Form Type')
                ->badge()
                ->sortable()
                ->formatStateUsing(fn (?string $state, $record): string => self::formTypeLabel($state, $record?->custom_form_id))
                ->color('info')
                ->toggleable(isToggledHiddenByDefault: false),

            // 2. List Number
            TextColumn::make('data.list_number')
                ->label(app()->getLocale() === 'km' ? 'លេខបញ្ជី' : 'List Number')
                ->placeholder('-')
                ->searchable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: false),

            // 3. Major
            TextColumn::make('major')
                ->label(app()->getLocale() === 'km' ? 'ផ្នែក/ជំនាញ' : 'Major')
                ->getStateUsing(fn ($record) => data_get($record->data, 'selected_major') ?? data_get($record->data, 'degree_level_major') ?? '-')
                ->placeholder('-')
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->where(function ($q) use ($search) {
                        $q->where('data->selected_major', 'like', "%{$search}%")
                          ->orWhere('data->degree_level_major', 'like', "%{$search}%");
                    });
                })
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: false),

            // 4. Gender
            TextColumn::make('data.gender')
                ->label(app()->getLocale() === 'km' ? 'ភេទ' : 'Gender')
                ->placeholder('-')
                ->formatStateUsing(fn (?string $state): string => match ($state) {
                    'male' => app()->getLocale() === 'km' ? 'ប្រុស' : 'Male',
                    'female' => app()->getLocale() === 'km' ? 'ស្រី' : 'Female',
                    default => filled($state) ? ucfirst($state) : '-',
                })
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: false),

            // 5. Phone Number
            TextColumn::make('data.phone_number')
                ->label(app()->getLocale() === 'km' ? 'លេខទូរស័ព្ទ' : 'Phone Number')
                ->placeholder('-')
                ->searchable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: false),

            // 6. Academic Year
            TextColumn::make('data.academic_year')
                ->label(app()->getLocale() === 'km' ? 'ឆ្នាំសិក្សា' : 'Academic Year')
                ->placeholder('-')
                ->searchable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: false),

            // 7. Class
            TextColumn::make('data.class')
                ->label(app()->getLocale() === 'km' ? 'ថ្នាក់' : 'Class')
                ->placeholder('-')
                ->searchable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: false),
        ];

        foreach ($additionalColumns as $column) {
            $columns[] = $column;
        }

        $columns[] = self::reviewStatusColumn()->toggleable(isToggledHiddenByDefault: false);

        $columns[] = TextColumn::make('created_at')
            ->label(__('review_applications.request_at'))
            ->dateTime('d-M-Y')
            ->color('gray')
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: false);

        return $columns;
    }

    protected static function reviewStatusColumn(): TextColumn
    {
        return TextColumn::make('review_status')
            ->label(__('review_applications.review_status'))
            ->badge()
            ->formatStateUsing(function ($state, $record): string {
                return match (self::entryStatus($record)) {
                    'passed', 'accepted', 'approved' => __('review_applications.statuses.accepted'),
                    'failed', 'rejected' => __('review_applications.statuses.rejected'),
                    'draft' => __('student_profile.save_as_draft'),
                    default => __('review_applications.statuses.pending'),
                };
            })
            ->color(function ($state, $record): string {
                return match (self::entryStatus($record)) {
                    'passed', 'accepted', 'approved' => 'success',
                    'failed', 'rejected' => 'danger',
                    'draft' => 'gray',
                    default => 'warning',
                };
            });
    }

    protected static function entryStatus($record): string
    {
        $dataStatus = strtolower((string) data_get($record->data, 'registration_status'));
        $reviewStatus = strtolower((string) ($record->review_status ?? 'pending'));

        if ($dataStatus === 'draft' || $reviewStatus === 'draft') {
            return 'draft';
        }

        if ($dataStatus === 'pending' || $reviewStatus === 'pending') {
            return 'pending';
        }

        return $reviewStatus ?: $dataStatus ?: 'pending';
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
        $columns = [
            TextColumn::make('data.form_selection')
                ->label(__('review_applications.form_type'))
                ->badge()
                ->sortable()
                ->formatStateUsing(fn (?string $state): string => self::formTypeLabel($state))
                ->color('info'),
        ];

        $nationalExamFormId = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
            ->where('slug', 'national-examination-registration')
            ->value('id');

        if ($nationalExamFormId) {
            $childForms = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
                ->where('custom_form_id', $nationalExamFormId)
                ->where('menu_placement', 'sub_item')
                ->whereNotNull('sub_item_type')
                ->where('is_active', true)
                ->get();

            foreach ($childForms as $childForm) {
                $fields = \Chanthoeun\FilamentCustomForms\Models\CustomFormField::query()
                    ->where('custom_form_id', $childForm->id)
                    ->whereNotIn('type', ['section', 'grid', 'fieldset', 'repeater', 'wizard', 'info'])
                    ->orderBy('sort')
                    ->get();

                foreach ($fields as $field) {
                    $key = (string) $field->name;

                    if (blank($key)) {
                        continue;
                    }

                    $column = TextColumn::make("data.{$key}")
                        ->label(self::transText($field->label ?: $key))
                        ->placeholder('-')
                        ->toggleable()
                        ->wrap();

                    if (self::isGeoColumn((string) $key)) {
                        $column->formatStateUsing(fn (mixed $state): string => self::geoLocationName($state));
                    }

                    $columns[] = $column;
                }
            }
        }

        $columns[] = self::reviewStatusColumn();

        $columns[] = TextColumn::make('created_at')
            ->label(__('review_applications.request_at'))
            ->dateTime('d M Y H:i')
            ->color('gray');

        $columns[] = TextColumn::make('reviewed_at')
            ->label(__('review_applications.reviewed_at'))
            ->dateTime('d M Y H:i')
            ->placeholder(__('review_applications.not_reviewed_yet'))
            ->color('info');

        return $columns;
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
            TextColumn::make('data.first_name_kh')->label('First Name (Khmer)')->placeholder('-'),
            TextColumn::make('data.last_name_kh')->label('Last Name (Khmer)')->placeholder('-'),
            TextColumn::make('data.date_of_birth')->label('Date of Birth')->formatStateUsing(fn (mixed $state): string => self::formatProfileDate($state))->placeholder('-'),
            TextColumn::make('data.exam_period')->label('Exam Date')->formatStateUsing(fn (mixed $state): string => self::formatProfileDate($state))->placeholder('-'),
            TextColumn::make('data.exam_center')->label('Exam Center')->placeholder('-'),
            TextColumn::make('data.current_occupation')->label('Current Occupation')->placeholder('-'),
            TextColumn::make('data.place_of_work')->label('Place of Work / Organization')->placeholder('-')->wrap(),
        ];
    }

    protected static function formatProfileDate(mixed $state): string
    {
        if (blank($state)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($state)->format('d-M-Y');
        } catch (\Throwable) {
            return (string) $state;
        }
    }

    protected static function getFilters(?string $formId): array
    {
        if (auth()->user()?->registration_type === 'student') {
            return [];
        }

        return [
            Filter::make('application_review_filters')
                ->label(new HtmlString('&nbsp;'))
                ->schema([
                    Select::make('form_selection')
                        ->label(__('review_applications.form_type'))
                        ->options(function () use ($formId): array {
                            return \Chanthoeun\FilamentCustomForms\Models\CustomFormEntry::query()
                                ->when($formId, fn ($query) => $query->where('custom_form_id', $formId))
                                ->whereNotNull('data->form_selection')
                                ->get(['data'])
                                ->pluck('data.form_selection')
                                ->filter()
                                ->unique()
                                ->mapWithKeys(fn ($item) => [
                                    (string) $item => self::formTypeLabel((string) $item, $formId),
                                ])
                                ->toArray();
                        })
                        ->native(false)
                        ->live(),

                    Select::make('review_status')
                        ->label(__('review_applications.review_status'))
                        ->options([
                            'pending' => __('review_applications.statuses.pending'),
                            'accepted' => __('review_applications.statuses.accepted'),
                            'rejected' => __('review_applications.statuses.rejected'),
                        ])
                        ->native(false)
                        ->live(),

                    Select::make('reviewed_year')
                        ->label(__('review_applications.reviewed_year'))
                        ->options(fn (): array => self::dynamicRequestReviewedYears($formId))
                        ->native(false)
                        ->live(),
                ])
                ->columns(4)
                ->columnSpanFull()
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            filled($data['form_selection'] ?? null),
                            fn (Builder $query): Builder => $query->where('data->form_selection', $data['form_selection'])
                        )
                        ->when(
                            filled($data['review_status'] ?? null),
                            function (Builder $query) use ($data): Builder {
                                $status = $data['review_status'];

                                if ($status === 'accepted') {
                                    return $query->whereIn('review_status', ['accepted', 'passed', 'approved']);
                                }

                                if ($status === 'rejected') {
                                    return $query->whereIn('review_status', ['rejected', 'failed']);
                                }

                                return $query->where(function ($q) {
                                    $q->where('review_status', 'pending')
                                        ->orWhereNull('review_status')
                                        ->orWhere('review_status', '');
                                });
                            }
                        )
                        ->when(
                            filled($data['reviewed_year'] ?? null),
                            function (Builder $query) use ($data): Builder {
                                return $query->where(function (Builder $query) use ($data): void {
                                    $query->whereYear('created_at', $data['reviewed_year'])
                                        ->orWhereYear('reviewed_at', $data['reviewed_year']);
                                });
                            }
                        )
                        ->when(
                            filled($data['reviewed_month'] ?? null),
                            fn (Builder $query): Builder => $query->whereMonth('reviewed_at', $data['reviewed_month'])
                        );
                }),
        ];
    }

    protected static function dynamicRequestReviewedYears(?string $formId): array
    {
        $rows = \Chanthoeun\FilamentCustomForms\Models\CustomFormEntry::query()
            ->when($formId, fn ($query) => $query->where('custom_form_id', $formId))
            ->get(['created_at', 'reviewed_at']);

        return $rows
            ->flatMap(function ($entry): array {
                $years = [];

                if ($entry->created_at) {
                    $years[] = \Carbon\Carbon::parse($entry->created_at)->format('Y');
                }

                if ($entry->reviewed_at) {
                    $years[] = \Carbon\Carbon::parse($entry->reviewed_at)->format('Y');
                }

                return $years;
            })
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn ($year): array => [(string) $year => (string) $year])
            ->toArray();
    }

    protected static function getRecordActions(): array
    {
        $actions = [
            EditAction::make()
                ->url(fn ($record): string => CustomFormEntryResource::getUrl('edit', [
                    'record' => $record,
                ]))
                ->visible(function ($record): bool {
                    if (self::currentPanelIsAdmin()) {
                        return false;
                    }

                    return self::entryStatus($record) === 'rejected';
                }),

            Action::make('save_draft')
                ->label(__('student_profile.save_as_draft'))
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->link()
                ->action(function ($record): void {
                    $data = is_array($record->data)
                        ? $record->data
                        : json_decode((string) $record->data, true);

                    $data = is_array($data) ? $data : [];
                    $data['registration_status'] = 'draft';

                    DB::table('custom_form_entries')
                        ->where('id', $record->id)
                        ->update([
                            'review_status' => 'draft',
                            'data' => json_encode($data),
                            'updated_at' => now(),
                        ]);

                    redirect(CustomFormEntryResource::getUrl('create', [
                        'form_id' => $record->custom_form_id,
                        'draft_id' => $record->id,
                    ]));
                })
                ->visible(fn ($record): bool =>
                    auth()->user()?->registration_type === 'student'
                    && self::recordIsNationalExam($record)
                    && self::entryStatus($record) === 'draft'
                ),
        ];

        if (self::currentPanelIsAdmin()) {
            $actions[] = Action::make('view_template_pdf')
                ->label(__('review_applications.view_pdf'))
                ->icon('heroicon-o-eye')
                ->color('info')
                ->modalHeading('View Application Review')
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->modalContent(fn ($record) => view('custom-form-entry-pdf-modal', [
                    'record' => $record,
                    'pdfUrl' => route('admin.custom-form-entries.pdf-inline', [
                        'entry' => $record->id,
                    ]),
                ]))
                ->extraModalFooterActions(fn ($record): array => [
                    Action::make('approve_from_view')
                        ->label(__('review_applications.statuses.accepted'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (): bool => self::entryStatus($record) === 'pending')
                        ->action(function () use ($record): void {
                            $data = is_array($record->data) ? $record->data : [];
                            $data['candidate_status'] = 'pending';
                            $data['registration_status'] = 'approved';

                            DB::table('custom_form_entries')
                                ->where('id', $record->id)
                                ->update([
                                    'review_status' => 'approved',
                                    'review_note' => null,
                                    'reviewed_by' => auth()->id(),
                                    'reviewed_at' => now(),
                                    'updated_at' => now(),
                                    'data' => json_encode($data),
                                ]);

                            $record->refresh();

                            self::notifyStudentNationalExamResult($record, 'approved', null);

                            Notification::make()
                                ->title('Application approved')
                                ->success()
                                ->send();

                            redirect(request()->header('Referer') ?: request()->fullUrl());
                        }),

                    Action::make('reject_from_view')
                        ->label(__('review_applications.statuses.send_back'))
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('danger')
                        ->form([
                            Textarea::make('review_note')
                                ->label(__('review_applications.review_note'))
                                ->required()
                                ->rows(4),
                        ])
                        ->visible(fn (): bool => self::entryStatus($record) === 'pending')
                        ->action(function (array $data) use ($record): void {
                            $recordData = is_array($record->data) ? $record->data : [];
                            $recordData['registration_status'] = 'rejected';

                            DB::table('custom_form_entries')
                                ->where('id', $record->id)
                                ->update([
                                    'review_status' => 'rejected',
                                    'review_note' => $data['review_note'] ?? null,
                                    'reviewed_by' => auth()->id(),
                                    'reviewed_at' => now(),
                                    'updated_at' => now(),
                                    'data' => json_encode($recordData),
                                ]);

                            $record->refresh();

                            self::notifyStudentNationalExamResult($record, 'rejected', $data['review_note'] ?? null);

                            Notification::make()
                                ->title('Application rejected')
                                ->danger()
                                ->send();

                            redirect(request()->header('Referer') ?: request()->fullUrl());
                        }),
                ])
                ->visible(fn ($record): bool =>
                    self::currentPanelIsAdmin()
                    && ! self::isProfileForm($record->custom_form_id)
                    && self::entryStatus($record) === 'pending'
                    && self::hasDocumentTemplate($record)
                );

            $actions[] = Action::make('accepted')
                ->label(__('review_applications.statuses.accepted'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn ($record): bool =>
                    self::currentPanelIsAdmin()
                    && ! self::isProfileForm($record->custom_form_id)
                    && self::entryStatus($record) === 'pending'
                    && ! self::hasDocumentTemplate($record)
                )
                ->action(function ($record): void {
                    $data = is_array($record->data) ? $record->data : [];
                    $data['candidate_status'] = 'pending';
                    $data['registration_status'] = 'approved';

                    DB::table('custom_form_entries')
                        ->where('id', $record->id)
                        ->update([
                            'review_status' => 'approved',
                            'review_note' => null,
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'updated_at' => now(),
                            'data' => json_encode($data),
                        ]);

                    $record->refresh();

                    self::notifyStudentNationalExamResult($record, 'approved', null);

                    Notification::make()
                        ->title('Application approved')
                        ->success()
                        ->send();
                });

            $actions[] = Action::make('rejected')
                ->label(__('review_applications.statuses.rejected'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn ($record): bool =>
                    self::currentPanelIsAdmin()
                    && ! self::isProfileForm($record->custom_form_id)
                    && self::entryStatus($record) === 'pending'
                    && ! self::hasDocumentTemplate($record)
                )
                ->form([
                    Textarea::make('review_note')
                        ->label(__('review_applications.review_note'))
                        ->required()
                        ->rows(4),
                ])
                ->action(function ($record, array $data): void {
                    $recordData = is_array($record->data) ? $record->data : [];
                    $recordData['registration_status'] = 'rejected';

                    DB::table('custom_form_entries')
                        ->where('id', $record->id)
                        ->update([
                            'review_status' => 'rejected',
                            'review_note' => $data['review_note'] ?? null,
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'updated_at' => now(),
                            'data' => json_encode($recordData),
                        ]);

                    $record->refresh();

                    self::notifyStudentNationalExamResult($record, 'rejected', $data['review_note'] ?? null);

                    Notification::make()
                        ->title('Application rejected')
                        ->danger()
                        ->send();
                });
        }

        if (class_exists(\Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::class)) {
            $actions[] = \Chanthoeun\FilamentDocumentBuilder\Tables\Actions\DownloadPdfAction::make('download_pdf')
                ->label(__('review_applications.download_pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->templateType(function ($record) {
                    $formSelection = strtolower((string) data_get($record->data, 'form_selection'));

                    if (filled($formSelection)) {
                        $subForm = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
                            ->where('custom_form_id', $record->custom_form_id)
                            ->where('menu_placement', 'sub_item')
                            ->where('sub_item_type', $formSelection)
                            ->first();

                        if ($subForm) {
                            return 'custom_form_' . $subForm->id;
                        }
                    }

                    return 'custom_form_' . $record->custom_form_id;
                })
                ->filename(fn ($record) => 'document-' . $record->id . '.pdf')
                ->visible(fn ($record): bool => self::canDownloadPdf($record));
        }

        return $actions;
    }
    protected static function canEdit($record): bool
    {
        $status = self::entryStatus($record);
        $slug = $record->customForm?->slug;

        if ($status === 'draft') {
            return true;
        }

        if ($slug === 'profile') {
            return ! self::studentHasAcceptedNationalExam();
        }

        return in_array($status, [
            '',
            'failed',
            'rejected',
        ], true);
    }

    protected static function studentHasAcceptedNationalExam(): bool
    {
        $userId = auth()->id();

        if (! $userId) {
            return false;
        }

        $nationalExamFormId = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
            ->where('slug', 'national-examination-registration')
            ->value('id');

        if (! $nationalExamFormId) {
            return false;
        }

        return \Chanthoeun\FilamentCustomForms\Models\CustomFormEntry::query()
            ->where('custom_form_id', $nationalExamFormId)
            ->whereIn('review_status', ['passed', 'accepted', 'approved'])
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
            ->exists();
    }

    protected static function canDownloadPdf($record): bool
    {
        $status = self::entryStatus($record);

        return in_array($status, [
            'passed',
            'accepted',
            'approved',
        ], true);
    }

    protected static function hasDocumentTemplate($record): bool
    {
        if (! class_exists(\Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::class)) {
            return false;
        }

        $formSelection = strtolower((string) data_get($record->data, 'form_selection'));

        if (filled($formSelection)) {
            $subForm = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
                ->where('custom_form_id', $record->custom_form_id)
                ->where('menu_placement', 'sub_item')
                ->where('sub_item_type', $formSelection)
                ->first();

            if ($subForm) {
                return \Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::query()
                    ->where('type', 'custom_form_' . $subForm->id)
                    ->exists();
            }
        }

        return \Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::query()
            ->where('type', 'custom_form_' . $record->custom_form_id)
            ->exists();
    }

    protected static function currentPanelIsAdmin(): bool
    {
        return auth()->user()?->registration_type === 'admin'
            || (
                \Filament\Facades\Filament::getCurrentPanel()
                && \Filament\Facades\Filament::getCurrentPanel()->getId() === 'admin'
            );
    }

    protected static function applyQueryConstraints(Builder $query, ?string $formId): Builder
    {
        $query->with(['creator', 'customForm']);

        if ($formId) {
            $query->where('custom_form_id', $formId);
        } else {
            $nationalExamFormId = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
                ->where('slug', 'national-examination-registration')
                ->value('id');

            if ($nationalExamFormId) {
                $query->where('custom_form_id', $nationalExamFormId);
            }
        }

        if (
            \Filament\Facades\Filament::getCurrentPanel()
            && \Filament\Facades\Filament::getCurrentPanel()->getId() === 'student'
        ) {
            $userId = auth()->id();

            if (! $userId) {
                return $query->whereRaw('1 = 0');
            }

            if (Schema::hasColumn('custom_form_entries', 'created_by')) {
                return $query->where('created_by', $userId);
            }

            if (Schema::hasColumn('custom_form_entries', 'user_id')) {
                return $query->where('user_id', $userId);
            }

            if (Schema::hasColumn('custom_form_entries', 'created_by_id')) {
                return $query->where('created_by_id', $userId);
            }

            return $query->whereRaw('1 = 0');
        }

        if (self::currentPanelIsAdmin()) {
            $query->where(function (Builder $query): void {
                $query
                    ->whereNull('review_status')
                    ->orWhere('review_status', '!=', 'draft');
            })
                ->where(function (Builder $query): void {
                    $query
                        ->whereNull('data->registration_status')
                        ->orWhere('data->registration_status', '!=', 'draft');
                });
        }

        return $query;
    }

    protected static function recordIsNationalExam($record): bool
    {
        return $record->customForm?->slug === 'national-examination-registration'
            || (int) $record->custom_form_id === (int) \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
                ->where('slug', 'national-examination-registration')
                ->value('id');
    }

    protected static function notifyStudentNationalExamResult($record, string $status, ?string $note = null): void
    {
        $student = self::getOwnerStudent($record);

        if (! $student) {
            return;
        }

        $formName = $record->customForm
            ? ($record->customForm->display_name ?: (app()->getLocale() === 'km' ? 'ពាក្យស្នើសុំ' : 'Application'))
            : (app()->getLocale() === 'km' ? 'ពាក្យស្នើសុំ' : 'Application');

        if ($status === 'approved') {
            Notification::make()
                ->title(
                    self::recordIsNationalExam($record)
                        ? NotificationLanguage::transForUser($student, 'review_applications.notifications.national_exam_approved_title')
                        : (app()->getLocale() === 'km' ? "ពាក្យស្នើសុំ {$formName} ត្រូវបានអនុម័ត" : "Application {$formName} Approved")
                )
                ->body(
                    self::recordIsNationalExam($record)
                        ? NotificationLanguage::transForUser($student, 'review_applications.notifications.national_exam_approved_body')
                        : (app()->getLocale() === 'km' ? "ពាក្យស្នើសុំ {$formName} របស់អ្នកត្រូវបានអនុម័តរួចរាល់ហើយ។" : "Your application for {$formName} has been approved.")
                )
                ->icon('heroicon-o-check-circle')
                ->iconColor('success')
                ->success()
                ->sendToDatabase($student);

            return;
        }

        Notification::make()
            ->title(
                self::recordIsNationalExam($record)
                    ? NotificationLanguage::transForUser($student, 'review_applications.notifications.national_exam_rejected_title')
                    : (app()->getLocale() === 'km' ? "ពាក្យស្នើសុំ {$formName} ត្រូវបានបដិសេធ" : "Application {$formName} Rejected")
            )
            ->body(
                self::recordIsNationalExam($record)
                    ? NotificationLanguage::transForUser($student, 'review_applications.notifications.national_exam_rejected_body', [
                        'note' => filled($note) ? $note : NotificationLanguage::transForUser($student, 'review_applications.notifications.no_reject_note'),
                    ])
                    : (app()->getLocale() === 'km'
                        ? "ពាក្យស្នើសុំ {$formName} របស់អ្នកត្រូវបានបដិសេធ។ មូលហេតុ៖ " . (filled($note) ? $note : 'គ្មាន')
                        : "Your application for {$formName} has been rejected. Reason: " . (filled($note) ? $note : 'None'))
            )
            ->icon('heroicon-o-x-circle')
            ->iconColor('danger')
            ->danger()
            ->sendToDatabase($student);
    }

    protected static function getOwnerStudent($record): ?User
    {
        if (! Schema::hasTable('users')) {
            return null;
        }

        foreach (['created_by', 'user_id', 'created_by_id'] as $column) {
            if (Schema::hasColumn('custom_form_entries', $column) && filled($record->{$column})) {
                return User::query()
                    ->where('id', $record->{$column})
                    ->where('registration_type', 'student')
                    ->first();
            }
        }

        return null;
    }

    protected static function transText(mixed $value): string
    {
        $locale = strtolower((string) app()->getLocale());

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);
        }

        if (is_array($value)) {
            return (string) (
                $value[$locale]
                ?? $value['km']
                ?? $value['kh']
                ?? $value['en']
                ?? collect($value)->first()
                ?? ''
            );
        }

        return (string) $value;
    }

    protected static function formTypeLabel(?string $state, ?string $parentFormId = null): string
    {
        if (blank($state)) {
            return '-';
        }

        $locale = app()->getLocale();

        $subForm = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
            ->where('menu_placement', 'sub_item')
            ->where('sub_item_type', $state)
            ->when($parentFormId, fn ($query) => $query->where('custom_form_id', $parentFormId))
            ->first();

        if ($subForm) {
            return self::transText($subForm->name);
        }

        return match ((string) $state) {
            'associate' => $locale === 'km' ? 'បរិញ្ញាបត្ររង' : 'Associate',
            'bachelor' => $locale === 'km' ? 'បរិញ្ញាបត្រ' : 'Bachelor',
            'master' => $locale === 'km' ? 'អនុបណ្ឌិត' : 'Master',
            'phd' => $locale === 'km' ? 'បណ្ឌិត' : 'PhD',
            default => filled($state) ? ucfirst((string) $state) : '-',
        };
    }

    protected static function isGeoColumn(string $key): bool
    {
        return in_array($key, [
            'birth_province_city',
            'birth_district_khan',
            'birth_commune_sangkat',
            'birth_village',

            'current_capital_province',
            'current_district_khan',
            'current_commune_sangkat',
            'current_village',

            'parents_capital_province',
            'parents_district_khan',
            'parents_commune_sangkat',
            'parents_village',
        ], true);
    }

    protected static function geoLocationName(mixed $state): string
    {
        if (blank($state)) {
            return '-';
        }

        $location = GeoLocation::query()->find($state);

        if (! $location) {
            return (string) $state;
        }

        return app()->getLocale() === 'km'
            ? ($location->name_kh ?: $location->name_en ?: '-')
            : ($location->name_en ?: $location->name_kh ?: '-');
    }
}
