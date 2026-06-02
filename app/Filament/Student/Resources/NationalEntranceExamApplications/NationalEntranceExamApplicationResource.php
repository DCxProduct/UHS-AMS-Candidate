<?php

namespace App\Filament\Student\Resources\NationalEntranceExamApplications;

use App\Filament\Student\Resources\NationalEntranceExamApplications\Pages\CreateNationalEntranceExamApplication;
use App\Filament\Student\Resources\NationalEntranceExamApplications\Pages\EditNationalEntranceExamApplication;
use App\Filament\Student\Resources\NationalEntranceExamApplications\Pages\ListNationalEntranceExamApplications;
use App\Filament\Student\Resources\NationalEntranceExamApplications\Pages\ViewNationalEntranceExamApplication;
use App\Models\NationalEntranceExamApplication;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden as HiddenField;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class NationalEntranceExamApplicationResource extends Resource
{
    protected static ?string $model = NationalEntranceExamApplication::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 51;

    protected static ?string $slug = 'national-entrance-exam-applications';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('national_entrance_exam_applications.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('national_entrance_exam_applications.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('national_entrance_exam_applications.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('national_entrance_exam_applications.plural_model_label');
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canEdit(Model $record): bool
    {
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $userId = Auth::id();

        if (! $userId) {
            return $query->whereRaw('1 = 0');
        }

        if (! DatabaseSchema::hasTable('national_entrance_exam_applications')) {
            return $query->whereRaw('1 = 0');
        }

        if (DatabaseSchema::hasColumn('national_entrance_exam_applications', 'created_by')) {
            return $query->where('created_by', $userId);
        }

        if (DatabaseSchema::hasColumn('national_entrance_exam_applications', 'user_id')) {
            return $query->where('user_id', $userId);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('national_entrance_exam_applications.form.section_title'))
                    ->schema([
                        /*
                        |--------------------------------------------------------------------------
                        | Main hidden fields used by the PDF Blade form
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('photo_path'),

                        HiddenField::make('application_no'),
                        HiddenField::make('registration_no'),
                        HiddenField::make('academic_year'),
                        HiddenField::make('exam_year'),

                        HiddenField::make('name'),
                        HiddenField::make('last_name'),
                        HiddenField::make('first_name'),
                        HiddenField::make('latin_name'),

                        HiddenField::make('gender'),
                        HiddenField::make('date_of_birth'),
                        HiddenField::make('age'),
                        HiddenField::make('nationality')->default(__('national_entrance_exam_applications.defaults.khmer')),
                        HiddenField::make('citizenship')->default(__('national_entrance_exam_applications.defaults.khmer')),

                        HiddenField::make('birth_village'),
                        HiddenField::make('birth_commune'),
                        HiddenField::make('birth_district'),
                        HiddenField::make('birth_province'),
                        HiddenField::make('birth_place'),

                        HiddenField::make('phone'),
                        HiddenField::make('telegram_phone'),
                        HiddenField::make('email'),
                        HiddenField::make('national_id'),
                        HiddenField::make('passport_no'),

                        HiddenField::make('current_house_no'),
                        HiddenField::make('current_street_no'),
                        HiddenField::make('current_group'),
                        HiddenField::make('current_village'),
                        HiddenField::make('current_commune'),
                        HiddenField::make('current_district'),
                        HiddenField::make('current_province'),
                        HiddenField::make('current_address'),

                        HiddenField::make('permanent_house_no'),
                        HiddenField::make('permanent_street_no'),
                        HiddenField::make('permanent_group'),
                        HiddenField::make('permanent_village'),
                        HiddenField::make('permanent_commune'),
                        HiddenField::make('permanent_district'),
                        HiddenField::make('permanent_province'),
                        HiddenField::make('permanent_address'),

                        HiddenField::make('education_level'),
                        HiddenField::make('high_school_name'),
                        HiddenField::make('bac_year'),
                        HiddenField::make('bac_exam_center'),
                        HiddenField::make('bac_room'),
                        HiddenField::make('bac_seat_no'),
                        HiddenField::make('bac_grade'),
                        HiddenField::make('bac_certificate_no'),
                        HiddenField::make('faculty_applied'),
                        HiddenField::make('major_applied'),

                        /*
                        |--------------------------------------------------------------------------
                        | Spouse / marital fields
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('marital_status'),
                        HiddenField::make('spouse_name'),
                        HiddenField::make('spouse_date_of_birth'),
                        HiddenField::make('spouse_nationality'),
                        HiddenField::make('spouse_occupation'),
                        HiddenField::make('spouse_phone'),
                        HiddenField::make('spouse_address'),

                        /*
                        |--------------------------------------------------------------------------
                        | Father fields
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('father_name'),
                        HiddenField::make('father_date_of_birth'),
                        HiddenField::make('father_age'),
                        HiddenField::make('father_nationality'),
                        HiddenField::make('father_occupation'),
                        HiddenField::make('father_phone'),
                        HiddenField::make('father_status'),
                        HiddenField::make('father_address'),

                        /*
                        |--------------------------------------------------------------------------
                        | Mother fields
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('mother_name'),
                        HiddenField::make('mother_date_of_birth'),
                        HiddenField::make('mother_age'),
                        HiddenField::make('mother_nationality'),
                        HiddenField::make('mother_occupation'),
                        HiddenField::make('mother_phone'),
                        HiddenField::make('mother_status'),
                        HiddenField::make('mother_address'),

                        /*
                        |--------------------------------------------------------------------------
                        | Guardian fields
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('guardian_name'),
                        HiddenField::make('guardian_relationship'),
                        HiddenField::make('guardian_phone'),
                        HiddenField::make('guardian_occupation'),
                        HiddenField::make('guardian_address'),

                        /*
                        |--------------------------------------------------------------------------
                        | Extra data used in pdf-form.blade.php
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('extra_data.birth_month'),
                        HiddenField::make('extra_data.birth_year'),
                        HiddenField::make('extra_data.workplace'),

                        HiddenField::make('extra_data.application_day'),
                        HiddenField::make('extra_data.application_month'),
                        HiddenField::make('extra_data.application_year'),

                        HiddenField::make('extra_data.nickname'),
                        HiddenField::make('extra_data.foreign_language'),

                        HiddenField::make('extra_data.bio_day'),
                        HiddenField::make('extra_data.bio_month'),
                        HiddenField::make('extra_data.bio_year'),

                        HiddenField::make('extra_data.sign_day'),
                        HiddenField::make('extra_data.sign_month'),
                        HiddenField::make('extra_data.sign_year'),

                        HiddenField::make('note'),

                        HiddenField::make('created_by')
                            ->default(fn (): ?int => auth()->id()),

                        SchemaView::make('filament.student.national-entrance-exam-applications.pdf-form')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('national_entrance_exam_applications.columns.no'))
                    ->rowIndex()
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('name')
                    ->label(__('national_entrance_exam_applications.columns.name'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('latin_name')
                    ->label(__('national_entrance_exam_applications.columns.latin_name'))
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('gender')
                    ->label(__('national_entrance_exam_applications.columns.gender'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'male', 'ប្រុស' => __('national_entrance_exam_applications.options.gender.male'),
                        'female', 'ស្រី' => __('national_entrance_exam_applications.options.gender.female'),
                        default => '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'male', 'ប្រុស' => 'info',
                        'female', 'ស្រី' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('phone')
                    ->label(__('national_entrance_exam_applications.columns.phone'))
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('academic_year')
                    ->label(__('national_entrance_exam_applications.columns.academic_year'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('exam_year')
                    ->label(__('national_entrance_exam_applications.columns.exam_year'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('national_entrance_exam_applications.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending' => __('national_entrance_exam_applications.options.status.pending'),
                        'submitted' => __('national_entrance_exam_applications.options.status.submitted'),
                        'reviewing' => __('national_entrance_exam_applications.options.status.reviewing'),
                        'approved' => __('national_entrance_exam_applications.options.status.approved'),
                        'rejected' => __('national_entrance_exam_applications.options.status.rejected'),
                        default => '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'submitted' => 'info',
                        'reviewing' => 'primary',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('national_entrance_exam_applications.columns.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('national_entrance_exam_applications.columns.updated_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('national_entrance_exam_applications.filters.status'))
                    ->options(__('national_entrance_exam_applications.options.status')),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label(__('national_entrance_exam_applications.actions.view'))
                    ->icon('heroicon-o-eye'),

                EditAction::make()
                    ->label(__('national_entrance_exam_applications.actions.edit'))
                    ->icon('heroicon-o-pencil-square'),

                DeleteAction::make()
                    ->label(__('national_entrance_exam_applications.actions.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->modalHeading(__('national_entrance_exam_applications.modal.delete_heading'))
                    ->modalDescription(__('national_entrance_exam_applications.modal.delete_description'))
                    ->modalSubmitActionLabel(__('national_entrance_exam_applications.modal.delete_submit')),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('national_entrance_exam_applications.empty.heading'))
            ->emptyStateDescription(__('national_entrance_exam_applications.empty.description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNationalEntranceExamApplications::route('/'),
            'create' => CreateNationalEntranceExamApplication::route('/create'),
            'view' => ViewNationalEntranceExamApplication::route('/{record}'),
            'edit' => EditNationalEntranceExamApplication::route('/{record}/edit'),
        ];
    }
}
