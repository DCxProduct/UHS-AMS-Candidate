<?php

namespace App\Filament\Student\Resources\CustomFormEntries;

use App\Filament\Student\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use App\Filament\Student\Resources\CustomFormEntries\Tables\CustomFormEntriesTable;
use BackedEnum;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Support\Str;
use Throwable;

class CustomFormEntryResource extends Resource
{
    protected static ?string $model = CustomFormEntry::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'custom-form-entries';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function getModelLabel(): string
    {
        return __('app.custom_form_entry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.custom_form_entries');
    }

    public static function getNavigationLabel(): string
    {
        return __('app.custom_form_entries');
    }

    public static function table(Table $table): Table
    {
        return CustomFormEntriesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (DatabaseSchema::hasColumn('custom_form_entries', 'created_by')) {
            $query->where('created_by', Auth::id());
        } elseif (DatabaseSchema::hasColumn('custom_form_entries', 'user_id')) {
            $query->where('user_id', Auth::id());
        }

        $formId = static::currentFormId();

        if ($formId) {
            $query->where('custom_form_id', $formId);
        }

        return $query;
    }

    public static function currentFormId(): ?int
    {
        $formId = request()->integer('custom_form_id');

        if (! $formId) {
            $filterFormId = data_get(request()->query('tableFilters'), 'custom_form_id.value');

            if (filled($filterFormId) && is_numeric($filterFormId)) {
                $formId = (int) $filterFormId;
            }
        }

        try {
            if ($formId) {
                session()->put('student_custom_form_entries.current_form_id', $formId);

                return $formId;
            }

            $sessionFormId = session()->get('student_custom_form_entries.current_form_id');

            if (filled($sessionFormId) && is_numeric($sessionFormId)) {
                return (int) $sessionFormId;
            }
        } catch (Throwable $e) {
            //
        }

        return null;
    }

    public static function currentForm(): ?CustomForm
    {
        $formId = static::currentFormId();

        if (! $formId) {
            return null;
        }

        return CustomForm::query()->find($formId);
    }

    public static function currentFormName(): string
    {
        $form = static::currentForm();

        if (! $form) {
            return __('app.form_entries');
        }

        $name = (string) (
            $form->name
            ?? $form->form_name
            ?? $form->title
            ?? __('app.form_entries')
        );

        $slug = (string) (
            $form->slug
            ?? Str::slug($name)
        );

        $key = 'app.forms_nav.' . $slug;

        $translated = __($key);

        if ($translated !== $key) {
            return $translated;
        }

        return $name;
    }

    public static function currentFormSlug(): ?string
    {
        return static::currentForm()?->slug;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomFormEntries::route('/'),
        ];
    }
}
