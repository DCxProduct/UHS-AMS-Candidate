<?php

namespace App\Filament\Student\Resources\MasterScienceNationalExitExamApplications;

use App\Filament\Student\Resources\MasterScienceNationalExitExamApplications\Pages\CreateMasterScienceNationalExitExamApplication;
use App\Filament\Student\Resources\MasterScienceNationalExitExamApplications\Pages\EditMasterScienceNationalExitExamApplication;
use App\Filament\Student\Resources\MasterScienceNationalExitExamApplications\Pages\ListMasterScienceNationalExitExamApplications;
use App\Models\MasterScienceNationalExitExamApplication;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden as HiddenField;
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

class MasterScienceNationalExitExamApplicationResource extends Resource
{
    protected static ?string $model = MasterScienceNationalExitExamApplication::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 52;

    protected static ?string $slug = 'master-science-national-exit-exam-applications';

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('master_science_national_exit_exam_applications.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.student_application');
    }

    public static function getModelLabel(): string
    {
        return __('master_science_national_exit_exam_applications.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('master_science_national_exit_exam_applications.plural_model_label');
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

    public static function canDelete(Model $record): bool
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

        if (! DatabaseSchema::hasTable('national_exit_exam_applications')) {
            return $query->whereRaw('1 = 0');
        }

        if (DatabaseSchema::hasColumn('national_exit_exam_applications', 'exam_type')) {
            $query->where('exam_type', 'national_exit_exam');
        }

        if (DatabaseSchema::hasColumn('national_exit_exam_applications', 'degree_level')) {
            $query->where('degree_level', 'master_science');
        }

        if (DatabaseSchema::hasColumn('national_exit_exam_applications', 'created_by')) {
            return $query->where('created_by', $userId);
        }

        if (DatabaseSchema::hasColumn('national_exit_exam_applications', 'user_id')) {
            return $query->where('user_id', $userId);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('master_science_national_exit_exam_applications.form.section_title'))
                    ->schema([
                        /*
                        |--------------------------------------------------------------------------
                        | System / Tracking
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('user_id')
                            ->default(fn (): ?int => auth()->id()),

                        HiddenField::make('created_by')
                            ->default(fn (): ?int => auth()->id()),

                        HiddenField::make('updated_by')
                            ->default(fn (): ?int => auth()->id()),

                        HiddenField::make('status')
                            ->default('draft'),

                        HiddenField::make('submitted_at'),

                        /*
                        |--------------------------------------------------------------------------
                        | Application / Exam Info
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('application_no'),
                        HiddenField::make('receipt_no'),
                        HiddenField::make('registration_no'),

                        HiddenField::make('exam_type')
                            ->default('national_exit_exam'),

                        HiddenField::make('degree_level')
                            ->default('master_science'),

                        HiddenField::make('training_course')
                            ->default('Master of Science'),

                        HiddenField::make('academic_year')
                            ->default('២០១៩ - ២០២០'),

                        HiddenField::make('exam_year'),
                        HiddenField::make('exam_session'),
                        HiddenField::make('exam_center'),
                        HiddenField::make('faculty_applied'),
                        HiddenField::make('major_applied'),

                        /*
                        |--------------------------------------------------------------------------
                        | Personal Info
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('name'),
                        HiddenField::make('last_name'),
                        HiddenField::make('first_name'),
                        HiddenField::make('latin_name'),

                        HiddenField::make('gender'),
                        HiddenField::make('date_of_birth'),
                        HiddenField::make('age'),

                        HiddenField::make('nationality')
                            ->default('ខ្មែរ'),

                        HiddenField::make('citizenship')
                            ->default('ខ្មែរ'),

                        HiddenField::make('religion'),
                        HiddenField::make('national_id'),
                        HiddenField::make('passport_no'),

                        /*
                        |--------------------------------------------------------------------------
                        | Birth Place
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('birth_village'),
                        HiddenField::make('birth_commune'),
                        HiddenField::make('birth_district'),
                        HiddenField::make('birth_province'),
                        HiddenField::make('birth_place'),

                        /*
                        |--------------------------------------------------------------------------
                        | Contact
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('phone'),
                        HiddenField::make('telegram_phone'),
                        HiddenField::make('email'),

                        /*
                        |--------------------------------------------------------------------------
                        | Current Address
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('current_house_no'),
                        HiddenField::make('current_street_no'),
                        HiddenField::make('current_group'),
                        HiddenField::make('current_village'),
                        HiddenField::make('current_commune'),
                        HiddenField::make('current_district'),
                        HiddenField::make('current_province'),
                        HiddenField::make('current_address'),

                        /*
                        |--------------------------------------------------------------------------
                        | Permanent Address
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('permanent_house_no'),
                        HiddenField::make('permanent_street_no'),
                        HiddenField::make('permanent_group'),
                        HiddenField::make('permanent_village'),
                        HiddenField::make('permanent_commune'),
                        HiddenField::make('permanent_district'),
                        HiddenField::make('permanent_province'),
                        HiddenField::make('permanent_address'),

                        /*
                        |--------------------------------------------------------------------------
                        | Education Info
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('education_level'),
                        HiddenField::make('school_name'),
                        HiddenField::make('university_name'),
                        HiddenField::make('institute_name'),

                        HiddenField::make('bac_year'),
                        HiddenField::make('bac_exam_center'),
                        HiddenField::make('bac_room'),
                        HiddenField::make('bac_seat_no'),
                        HiddenField::make('bac_grade'),
                        HiddenField::make('bac_certificate_no'),

                        HiddenField::make('graduation_year'),
                        HiddenField::make('degree_certificate_no'),
                        HiddenField::make('transcript_no'),

                        /*
                        |--------------------------------------------------------------------------
                        | Current Job / Workplace
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('current_job'),
                        HiddenField::make('workplace'),
                        HiddenField::make('position'),

                        /*
                        |--------------------------------------------------------------------------
                        | Spouse
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('marital_status'),
                        HiddenField::make('spouse_name'),
                        HiddenField::make('spouse_date_of_birth'),
                        HiddenField::make('spouse_age'),
                        HiddenField::make('spouse_nationality'),
                        HiddenField::make('spouse_occupation'),
                        HiddenField::make('spouse_phone'),
                        HiddenField::make('spouse_address'),

                        /*
                        |--------------------------------------------------------------------------
                        | Father
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
                        | Mother
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
                        | Guardian
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('guardian_name'),
                        HiddenField::make('guardian_relationship'),
                        HiddenField::make('guardian_phone'),
                        HiddenField::make('guardian_occupation'),
                        HiddenField::make('guardian_address'),

                        /*
                        |--------------------------------------------------------------------------
                        | Receipt Info
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('receipt_date'),
                        HiddenField::make('receipt_receiver_name'),
                        HiddenField::make('receipt_receiver_position'),

                        /*
                        |--------------------------------------------------------------------------
                        | Required Documents
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('has_application_form')
                            ->default(false),

                        HiddenField::make('has_biography')
                            ->default(false),

                        HiddenField::make('has_certificate')
                            ->default(false),

                        HiddenField::make('has_transcript')
                            ->default(false),

                        HiddenField::make('has_permission_letter')
                            ->default(false),

                        HiddenField::make('has_osce_result')
                            ->default(false),

                        HiddenField::make('has_photo_4x6')
                            ->default(false),

                        HiddenField::make('has_other_document')
                            ->default(false),

                        /*
                        |--------------------------------------------------------------------------
                        | Files
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('photo_path'),
                        HiddenField::make('signature_path'),
                        HiddenField::make('generated_pdf_path'),

                        HiddenField::make('receipt_file'),
                        HiddenField::make('application_form_file'),
                        HiddenField::make('biography_file'),
                        HiddenField::make('certificate_file'),
                        HiddenField::make('transcript_file'),
                        HiddenField::make('permission_letter_file'),
                        HiddenField::make('osce_result_file'),
                        HiddenField::make('other_document_file'),

                        /*
                        |--------------------------------------------------------------------------
                        | JSON Data
                        |--------------------------------------------------------------------------
                        */
                        HiddenField::make('children'),
                        HiddenField::make('siblings'),
                        HiddenField::make('education_histories'),
                        HiddenField::make('work_histories'),
                        HiddenField::make('document_checklist'),
                        HiddenField::make('extra_data'),

                        HiddenField::make('note'),

                        /*
                        |--------------------------------------------------------------------------
                        | PDF-like Blade Form
                        |--------------------------------------------------------------------------
                        */
                        SchemaView::make('filament.student.master-science-national-exit-exam-applications.pdf-form')
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
                    ->label(__('master_science_national_exit_exam_applications.columns.no'))
                    ->rowIndex()
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('application_no')
                    ->label(__('master_science_national_exit_exam_applications.columns.application_no'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('receipt_no')
                    ->label(__('master_science_national_exit_exam_applications.columns.receipt_no'))
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('name')
                    ->label(__('master_science_national_exit_exam_applications.columns.full_name_kh'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('latin_name')
                    ->label(__('master_science_national_exit_exam_applications.columns.full_name_latin'))
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('gender')
                    ->label(__('master_science_national_exit_exam_applications.columns.gender'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'male', 'ប្រុស' => __('master_science_national_exit_exam_applications.options.gender.male'),
                        'female', 'ស្រី' => __('master_science_national_exit_exam_applications.options.gender.female'),
                        default => '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'male', 'ប្រុស' => 'info',
                        'female', 'ស្រី' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('training_course')
                    ->label(__('master_science_national_exit_exam_applications.columns.training_course'))
                    ->placeholder(__('master_science_national_exit_exam_applications.placeholders.training_course'))
                    ->toggleable(),

                TextColumn::make('degree_level')
                    ->label(__('master_science_national_exit_exam_applications.columns.degree_level'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'master_science' => __('master_science_national_exit_exam_applications.options.degree_level.master_science'),
                        default => $state ?: '-',
                    })
                    ->color('success')
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label(__('master_science_national_exit_exam_applications.columns.phone'))
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('academic_year')
                    ->label(__('master_science_national_exit_exam_applications.columns.academic_year'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('exam_year')
                    ->label(__('master_science_national_exit_exam_applications.columns.exam_year'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('master_science_national_exit_exam_applications.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => __('master_science_national_exit_exam_applications.options.status.draft'),
                        'pending' => __('master_science_national_exit_exam_applications.options.status.pending'),
                        'submitted' => __('master_science_national_exit_exam_applications.options.status.submitted'),
                        'reviewing' => __('master_science_national_exit_exam_applications.options.status.reviewing'),
                        'approved' => __('master_science_national_exit_exam_applications.options.status.approved'),
                        'rejected' => __('master_science_national_exit_exam_applications.options.status.rejected'),
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
                    ->label(__('master_science_national_exit_exam_applications.columns.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('master_science_national_exit_exam_applications.columns.updated_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('master_science_national_exit_exam_applications.filters.status'))
                    ->options(__('master_science_national_exit_exam_applications.options.status')),

                SelectFilter::make('gender')
                    ->label(__('master_science_national_exit_exam_applications.filters.gender'))
                    ->options(__('master_science_national_exit_exam_applications.options.gender')),

                SelectFilter::make('academic_year')
                    ->label(__('master_science_national_exit_exam_applications.filters.academic_year'))
                    ->options(__('master_science_national_exit_exam_applications.options.academic_year')),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('master_science_national_exit_exam_applications.actions.edit'))
                    ->icon('heroicon-o-pencil-square'),

                DeleteAction::make()
                    ->label(__('master_science_national_exit_exam_applications.actions.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->modalHeading(__('master_science_national_exit_exam_applications.modal.delete_heading'))
                    ->modalDescription(__('master_science_national_exit_exam_applications.modal.delete_description'))
                    ->modalSubmitActionLabel(__('master_science_national_exit_exam_applications.modal.delete_submit')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('master_science_national_exit_exam_applications.actions.delete_selected')),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('master_science_national_exit_exam_applications.empty.heading'))
            ->emptyStateDescription(__('master_science_national_exit_exam_applications.empty.description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMasterScienceNationalExitExamApplications::route('/'),
            'create' => CreateMasterScienceNationalExitExamApplication::route('/create'),
            'edit' => EditMasterScienceNationalExitExamApplication::route('/{record}/edit'),
        ];
    }
}