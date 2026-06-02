<?php

namespace App\Filament\Student\Resources\NationalExitExamApplications;

use App\Filament\Student\Resources\NationalExitExamApplications\Pages\CreateNationalExitExamApplication;
use App\Filament\Student\Resources\NationalExitExamApplications\Pages\EditNationalExitExamApplication;
use App\Filament\Student\Resources\NationalExitExamApplications\Pages\ListNationalExitExamApplications;
use App\Models\NationalExitExamApplication;
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

class NationalExitExamApplicationResource extends Resource
{
    protected static ?string $model = NationalExitExamApplication::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 51;

    protected static ?string $slug = 'national-exit-exam-applications';

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $recordTitleAttribute = 'full_name_kh';

    public static function getNavigationLabel(): string
    {
        return __('national_exit_exam_applications.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.student_application');
    }

    public static function getModelLabel(): string
    {
        return __('national_exit_exam_applications.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('national_exit_exam_applications.plural_model_label');
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

        if (! DatabaseSchema::hasTable('national_exit_exam_applications')) {
            return $query->whereRaw('1 = 0');
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
                Section::make(__('national_exit_exam_applications.form.section_title'))
                    ->schema([
                        HiddenField::make('user_id')->default(fn (): ?int => auth()->id()),
                        HiddenField::make('created_by')->default(fn (): ?int => auth()->id()),
                        HiddenField::make('updated_by')->default(fn (): ?int => auth()->id()),
                        HiddenField::make('reviewed_by'),

                        HiddenField::make('application_no'),
                        HiddenField::make('receipt_no'),
                        HiddenField::make('academic_year')->default('២០២៥-២០២៦'),
                        HiddenField::make('exam_year')->default('២០២៦'),
                        HiddenField::make('exam_session')->default('២៣-២៤ ខែឧសភា ឆ្នាំ២០២៦'),
                        HiddenField::make('application_date'),

                        HiddenField::make('faculty_name'),
                        HiddenField::make('major_name'),
                        HiddenField::make('degree_level'),
                        HiddenField::make('exam_class'),
                        HiddenField::make('candidate_from'),
                        HiddenField::make('completed_study_level'),
                        HiddenField::make('completed_study_at'),
                        HiddenField::make('school_name'),
                        HiddenField::make('school_location'),
                        HiddenField::make('foreign_language'),

                        HiddenField::make('full_name_kh'),
                        HiddenField::make('full_name_latin'),
                        HiddenField::make('gender'),
                        HiddenField::make('nationality')->default(__('national_exit_exam_applications.defaults.khmer')),
                        HiddenField::make('date_of_birth'),

                        HiddenField::make('birth_place'),
                        HiddenField::make('birth_village_group'),
                        HiddenField::make('birth_commune'),
                        HiddenField::make('birth_district'),
                        HiddenField::make('birth_province'),

                        HiddenField::make('current_address'),
                        HiddenField::make('current_village_group'),
                        HiddenField::make('current_commune'),
                        HiddenField::make('current_district'),
                        HiddenField::make('current_province'),

                        HiddenField::make('phone'),
                        HiddenField::make('email'),
                        HiddenField::make('contact_address'),
                        HiddenField::make('contact_phone'),

                        HiddenField::make('marital_status'),
                        HiddenField::make('spouse_name'),
                        HiddenField::make('spouse_date_of_birth'),
                        HiddenField::make('spouse_nationality'),
                        HiddenField::make('spouse_occupation'),

                        HiddenField::make('father_name'),
                        HiddenField::make('father_status'),
                        HiddenField::make('father_age'),
                        HiddenField::make('father_birth_place'),
                        HiddenField::make('father_nationality'),
                        HiddenField::make('father_occupation'),

                        HiddenField::make('mother_name'),
                        HiddenField::make('mother_status'),
                        HiddenField::make('mother_age'),
                        HiddenField::make('mother_birth_place'),
                        HiddenField::make('mother_nationality'),
                        HiddenField::make('mother_occupation'),

                        HiddenField::make('photo'),
                        HiddenField::make('national_id_file'),
                        HiddenField::make('birth_certificate_file'),
                        HiddenField::make('diploma_file'),
                        HiddenField::make('transcript_file'),
                        HiddenField::make('other_document_file'),

                        HiddenField::make('payment_amount')->default(100000),
                        HiddenField::make('payment_status')->default('unpaid'),
                        HiddenField::make('payment_date'),
                        HiddenField::make('payment_receipt_file'),

                        HiddenField::make('request_reason'),
                        HiddenField::make('notes'),
                        HiddenField::make('rejected_reason'),

                        HiddenField::make('status')->default('draft'),
                        HiddenField::make('submitted_at'),
                        HiddenField::make('reviewed_at'),

                        SchemaView::make('filament.student.national-exit-exam-applications.pdf-form')
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
                    ->label(__('national_exit_exam_applications.columns.no'))
                    ->rowIndex()
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('application_no')
                    ->label(__('national_exit_exam_applications.columns.application_no'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('receipt_no')
                    ->label(__('national_exit_exam_applications.columns.receipt_no'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('full_name_kh')
                    ->label(__('national_exit_exam_applications.columns.full_name_kh'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('full_name_latin')
                    ->label(__('national_exit_exam_applications.columns.full_name_latin'))
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('gender')
                    ->label(__('national_exit_exam_applications.columns.gender'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'male', 'ប្រុស' => __('national_exit_exam_applications.options.gender.male'),
                        'female', 'ស្រី' => __('national_exit_exam_applications.options.gender.female'),
                        default => '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'male', 'ប្រុស' => 'info',
                        'female', 'ស្រី' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('phone')
                    ->label(__('national_exit_exam_applications.columns.phone'))
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('academic_year')
                    ->label(__('national_exit_exam_applications.columns.academic_year'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('exam_year')
                    ->label(__('national_exit_exam_applications.columns.exam_year'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('national_exit_exam_applications.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => __('national_exit_exam_applications.options.status.draft'),
                        'pending' => __('national_exit_exam_applications.options.status.pending'),
                        'submitted' => __('national_exit_exam_applications.options.status.submitted'),
                        'reviewing' => __('national_exit_exam_applications.options.status.reviewing'),
                        'approved' => __('national_exit_exam_applications.options.status.approved'),
                        'rejected' => __('national_exit_exam_applications.options.status.rejected'),
                        default => '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'submitted' => 'info',
                        'reviewing' => 'primary',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('national_exit_exam_applications.columns.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('national_exit_exam_applications.columns.status'))
                    ->options(__('national_exit_exam_applications.options.status')),

                SelectFilter::make('gender')
                    ->label(__('national_exit_exam_applications.columns.gender'))
                    ->options(__('national_exit_exam_applications.options.gender')),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('national_exit_exam_applications.actions.edit'))
                    ->icon('heroicon-o-pencil-square'),

                DeleteAction::make()
                    ->label(__('national_exit_exam_applications.actions.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->modalHeading(__('national_exit_exam_applications.modal.delete_heading'))
                    ->modalDescription(__('national_exit_exam_applications.modal.delete_description'))
                    ->modalSubmitActionLabel(__('national_exit_exam_applications.modal.delete_submit')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('national_exit_exam_applications.actions.delete_selected')),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('national_exit_exam_applications.empty.heading'))
            ->emptyStateDescription(__('national_exit_exam_applications.empty.description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNationalExitExamApplications::route('/'),
            'create' => CreateNationalExitExamApplication::route('/create'),
            'edit' => EditNationalExitExamApplication::route('/{record}/edit'),
        ];
    }
}
