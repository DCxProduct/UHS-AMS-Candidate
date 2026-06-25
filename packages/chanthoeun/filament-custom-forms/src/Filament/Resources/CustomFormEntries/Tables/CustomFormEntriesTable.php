<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Tables;

use App\Support\NotificationLanguage;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Select;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use App\Models\User;
use Illuminate\Support\Facades\Schema;


class CustomFormEntriesTable
{
    public static function configure(Table $table): Table
    {
        $formId = self::getFormId($table);

        return $table
            ->recordAction(null)
            ->recordUrl(function ($record): ?string {
                $status = strtolower((string) ($record->review_status ?? 'pending'));

                if ($status === 'pending' || $status === '') {
                    return \Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource::getUrl('edit', [
                        'record' => $record,
                    ]);
                }

                return null;
            })
            ->columns(self::getColumns($formId))
            ->filters(self::getFilters($formId), layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns(4)
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
        if (empty($formId) || self::isNationalExaminationForm($formId)) {
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
            if ($key === 'registration_status') {
                continue;
            }

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

            $column = TextColumn::make("data.{$key}")
                ->label($key === 'form_selection' ? 'Form Type' : \Illuminate\Support\Str::headline($key));

            if ($key === 'form_selection') {
                $column
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state));
            }

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
            $columns[] = self::reviewStatusColumn();

            $columns[] = TextColumn::make('reviewed_at')
                ->label(__('review_applications.reviewed_at'))
                ->dateTime('d M Y H:i')
                ->placeholder(__('review_applications.not_reviewed_yet'))
                ->color('info')
                ->sortable();
        }

        return $columns;
    }

    protected static function reviewStatusColumn(): TextColumn
    {
        return TextColumn::make('review_status')
            ->label(__('review_applications.review_status'))
            ->badge()
            ->formatStateUsing(fn (?string $state): string => match (strtolower($state ?? 'pending')) {
                'passed',
                'accepted',
                'approved' => __('review_applications.statuses.accepted'),

                'failed',
                'rejected' => __('review_applications.statuses.rejected'),

                default => __('review_applications.statuses.pending'),
            })
            ->color(fn (?string $state): string => match (strtolower($state ?? 'pending')) {
                'passed',
                'accepted',
                'approved' => 'success',

                'failed',
                'rejected' => 'danger',

                default => 'warning',
            });
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
                ->label(__('review_applications.student_id'))
                ->placeholder('-')
                ->wrap(),

            TextColumn::make('data.form_selection')
                ->label(__('review_applications.form_type'))
                ->badge()
                ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state))
                ->color('info'),

            TextColumn::make('data.national_registration_number')
                ->label(__('review_applications.national_registration_number'))
                ->placeholder('-')
                ->wrap(),

            TextColumn::make('data.first_name_kh')
                ->label(__('review_applications.first_name_kh'))
                ->placeholder('-')
                ->wrap(),

            TextColumn::make('data.last_name_kh')
                ->label(__('review_applications.last_name_kh'))
                ->placeholder('-')
                ->wrap(),

            self::reviewStatusColumn(),

            TextColumn::make('created_at')
                ->label(__('review_applications.request_at'))
                ->dateTime('d M Y H:i')
                ->color('gray')
                ->sortable(),

            TextColumn::make('reviewed_at')
                ->label(__('review_applications.reviewed_at'))
                ->dateTime('d M Y H:i')
                ->placeholder(__('review_applications.not_reviewed_yet'))
                ->color('info'),
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
                ->formatStateUsing(fn (mixed $state): string => self::formatProfileDate($state))
                ->placeholder('-'),

            TextColumn::make('data.exam_period')
                ->label('Exam Date')
                ->formatStateUsing(fn (mixed $state): string => self::formatProfileDate($state))
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
        if (auth()->user()?->registration_type === 'student') {
            return [];
        }

        if ($formId && ! self::isNationalExaminationForm($formId)) {
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
                                    (string) $item => ucfirst((string) $item),
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
                        ->options(
                            collect(range(2025, 2050))
                                ->mapWithKeys(fn ($year) => [(string) $year => (string) $year])
                                ->toArray()
                        )
                        ->native(false)
                        ->live(),
                ])
                ->columns(3)
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
                            fn (Builder $query): Builder => $query->whereYear('reviewed_at', $data['reviewed_year'])
                        )
                        ->when(
                            filled($data['reviewed_month'] ?? null),
                            fn (Builder $query): Builder => $query->whereMonth('reviewed_at', $data['reviewed_month'])
                        );
                }),
        ];
    }

    protected static function getRecordActions(): array
    {
        $actions = [
            EditAction::make()
                ->visible(function ($record): bool {
                    if (self::currentPanelIsAdmin()) {
                        return false;
                    }

                    return self::canEdit($record);
                }),
        ];

        if (self::currentPanelIsAdmin()) {
            $actions[] = Action::make('accepted')
                ->label(__('review_applications.statuses.accepted'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn ($record): bool =>
                    self::recordIsNationalExam($record)
                    && ! in_array(strtolower((string) $record->review_status), ['passed', 'accepted', 'approved'], true)
                )
                ->action(function ($record): void {
                    $data = is_array($record->data) ? $record->data : [];
                    $data['candidate_status'] = 'pending';

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

                    self::notifyStudentNationalExamResult(
                        record: $record,
                        status: 'approved',
                        note: null,
                    );

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
                    self::recordIsNationalExam($record)
                    && ! in_array(strtolower((string) $record->review_status), ['approved', 'accepted', 'passed', 'failed', 'rejected'], true)
                )
                ->form([
                    Textarea::make('review_note')
                        ->label(__('review_applications.review_note'))
                        ->required()
                        ->rows(4),
                ])
                ->action(function ($record, array $data): void {
                    DB::table('custom_form_entries')
                        ->where('id', $record->id)
                        ->update([
                            'review_status' => 'rejected',
                            'review_note' => $data['review_note'] ?? null,
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'updated_at' => now(),
                        ]);

                    $record->refresh();

                    self::notifyStudentNationalExamResult(
                        record: $record,
                        status: 'rejected',
                        note: $data['review_note'] ?? null,
                    );

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
        $status = strtolower((string) ($record->review_status ?? 'pending'));
        $slug = $record->customForm?->slug;

        if ($slug === 'profile') {
            return ! self::studentHasAcceptedNationalExam();
        }

        return in_array($status, [
            '',
            'pending',
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
                if (\Illuminate\Support\Facades\Schema::hasColumn('custom_form_entries', 'created_by')) {
                    $query->orWhere('created_by', $userId);
                }

                if (\Illuminate\Support\Facades\Schema::hasColumn('custom_form_entries', 'user_id')) {
                    $query->orWhere('user_id', $userId);
                }

                if (\Illuminate\Support\Facades\Schema::hasColumn('custom_form_entries', 'created_by_id')) {
                    $query->orWhere('created_by_id', $userId);
                }
            })
            ->exists();
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

        if ($status === 'approved') {
            Notification::make()
                ->title(NotificationLanguage::transForUser($student, 'review_applications.notifications.national_exam_approved_title'))
                ->body(NotificationLanguage::transForUser($student, 'review_applications.notifications.national_exam_approved_body'))
                ->icon('heroicon-o-check-circle')
                ->iconColor('success')
                ->success()
                ->sendToDatabase($student);

            return;
        }

        Notification::make()
            ->title(NotificationLanguage::transForUser($student, 'review_applications.notifications.national_exam_rejected_title'))
            ->body(NotificationLanguage::transForUser($student, 'review_applications.notifications.national_exam_rejected_body', [
                'note' => filled($note) ? $note : NotificationLanguage::transForUser($student, 'review_applications.notifications.no_reject_note'),
            ]))
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

        $studentId = null;

        foreach (['created_by', 'user_id', 'created_by_id'] as $column) {
            if (Schema::hasColumn('custom_form_entries', $column) && filled($record->{$column})) {
                $studentId = $record->{$column};
                break;
            }
        }

        if (! $studentId) {
            return null;
        }

        return User::query()
            ->where('id', $studentId)
            ->where('registration_type', 'student')
            ->first();
    }
}
