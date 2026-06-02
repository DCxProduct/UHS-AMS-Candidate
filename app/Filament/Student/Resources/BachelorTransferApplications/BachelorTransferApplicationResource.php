<?php

namespace App\Filament\Student\Resources\BachelorTransferApplications;

use App\Filament\Student\Resources\BachelorTransferApplications\Pages\CreateBachelorTransferApplication;
use App\Filament\Student\Resources\BachelorTransferApplications\Pages\EditBachelorTransferApplication;
use App\Filament\Student\Resources\BachelorTransferApplications\Pages\ListBachelorTransferApplications;
use App\Models\BachelorTransferApplication;
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

class BachelorTransferApplicationResource extends Resource
{
    protected static ?string $model = BachelorTransferApplication::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 51;

    protected static ?string $slug = 'bachelor-transfer-applications';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'full_name_kh';

    public static function getNavigationLabel(): string
    {
        return __('bachelor_transfer_applications.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.student_application');
    }

    public static function getModelLabel(): string
    {
        return __('bachelor_transfer_applications.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('bachelor_transfer_applications.plural_model_label');
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

        if (! DatabaseSchema::hasTable('bachelor_transfer_applications')) {
            return $query->whereRaw('1 = 0');
        }

        if (DatabaseSchema::hasColumn('bachelor_transfer_applications', 'created_by')) {
            return $query->where('created_by', $userId);
        }

        if (DatabaseSchema::hasColumn('bachelor_transfer_applications', 'user_id')) {
            return $query->where('user_id', $userId);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('bachelor_transfer_applications.form.section_title'))
                    ->schema([
                        HiddenField::make('user_id')
                            ->default(fn (): ?int => auth()->id()),

                        HiddenField::make('created_by')
                            ->default(fn (): ?int => auth()->id()),

                        HiddenField::make('updated_by')
                            ->default(fn (): ?int => auth()->id()),

                        HiddenField::make('reviewed_by'),

                        HiddenField::make('application_no'),
                        HiddenField::make('receipt_no'),
                        HiddenField::make('academic_year')
                            ->default(__('bachelor_transfer_applications.defaults.academic_year')),
                        HiddenField::make('application_date'),

                        HiddenField::make('transfer_from_university'),
                        HiddenField::make('transfer_from_faculty'),
                        HiddenField::make('transfer_from_major'),
                        HiddenField::make('transfer_from_year'),
                        HiddenField::make('transfer_from_semester'),

                        HiddenField::make('transfer_to_university')
                            ->default(__('bachelor_transfer_applications.defaults.transfer_to_university')),
                        HiddenField::make('transfer_to_faculty'),
                        HiddenField::make('transfer_to_major'),
                        HiddenField::make('transfer_to_year'),
                        HiddenField::make('transfer_to_semester'),

                        HiddenField::make('family_name_kh'),
                        HiddenField::make('given_name_kh'),
                        HiddenField::make('full_name_kh'),

                        HiddenField::make('family_name_en'),
                        HiddenField::make('given_name_en'),
                        HiddenField::make('full_name_en'),

                        HiddenField::make('gender'),
                        HiddenField::make('date_of_birth'),
                        HiddenField::make('place_of_birth'),
                        HiddenField::make('nationality')
                            ->default(__('bachelor_transfer_applications.defaults.nationality')),
                        HiddenField::make('marital_status'),

                        HiddenField::make('phone'),
                        HiddenField::make('telegram_phone'),
                        HiddenField::make('email'),
                        HiddenField::make('current_address'),
                        HiddenField::make('permanent_address'),

                        HiddenField::make('national_id_card'),
                        HiddenField::make('passport_no'),
                        HiddenField::make('student_card_no'),

                        HiddenField::make('father_name'),
                        HiddenField::make('father_occupation'),
                        HiddenField::make('father_phone'),

                        HiddenField::make('mother_name'),
                        HiddenField::make('mother_occupation'),
                        HiddenField::make('mother_phone'),

                        HiddenField::make('guardian_name'),
                        HiddenField::make('guardian_relationship'),
                        HiddenField::make('guardian_phone'),
                        HiddenField::make('guardian_address'),

                        HiddenField::make('high_school_name'),
                        HiddenField::make('high_school_province'),
                        HiddenField::make('bacii_year'),
                        HiddenField::make('bacii_grade'),
                        HiddenField::make('bacii_exam_center'),

                        HiddenField::make('previous_student_id'),
                        HiddenField::make('previous_academic_year'),
                        HiddenField::make('previous_result'),
                        HiddenField::make('previous_gpa'),

                        HiddenField::make('education_records'),
                        HiddenField::make('family_records'),
                        HiddenField::make('attachment_checklist'),

                        HiddenField::make('photo'),
                        HiddenField::make('national_id_file'),
                        HiddenField::make('passport_file'),
                        HiddenField::make('bacii_certificate_file'),
                        HiddenField::make('transcript_file'),
                        HiddenField::make('student_card_file'),
                        HiddenField::make('transfer_letter_file'),
                        HiddenField::make('other_document_file'),

                        HiddenField::make('request_reason'),
                        HiddenField::make('student_declaration'),
                        HiddenField::make('admin_note'),

                        HiddenField::make('status')->default('draft'),
                        HiddenField::make('submitted_at'),
                        HiddenField::make('reviewed_at'),

                        HiddenField::make('student_signed_date'),
                        HiddenField::make('admin_signed_date'),
                        HiddenField::make('student_signature'),
                        HiddenField::make('admin_signature'),

                        SchemaView::make('filament.student.bachelor-transfer-applications.pdf-form')
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
                    ->label(__('bachelor_transfer_applications.columns.no'))
                    ->rowIndex()
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('application_no')
                    ->label(__('bachelor_transfer_applications.columns.application_no'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('receipt_no')
                    ->label(__('bachelor_transfer_applications.columns.receipt_no'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('full_name_kh')
                    ->label(__('bachelor_transfer_applications.columns.full_name_kh'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('full_name_en')
                    ->label(__('bachelor_transfer_applications.columns.full_name_en'))
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('gender')
                    ->label(__('bachelor_transfer_applications.columns.gender'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'male', 'ប្រុស' => __('bachelor_transfer_applications.options.gender.male'),
                        'female', 'ស្រី' => __('bachelor_transfer_applications.options.gender.female'),
                        default => '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'male', 'ប្រុស' => 'info',
                        'female', 'ស្រី' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('transfer_from_university')
                    ->label(__('bachelor_transfer_applications.columns.transfer_from_university'))
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('transfer_to_major')
                    ->label(__('bachelor_transfer_applications.columns.transfer_to_major'))
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label(__('bachelor_transfer_applications.columns.phone'))
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('status')
                    ->label(__('bachelor_transfer_applications.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => __('bachelor_transfer_applications.options.status.draft'),
                        'submitted' => __('bachelor_transfer_applications.options.status.submitted'),
                        'reviewing' => __('bachelor_transfer_applications.options.status.reviewing'),
                        'approved' => __('bachelor_transfer_applications.options.status.approved'),
                        'rejected' => __('bachelor_transfer_applications.options.status.rejected'),
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
                    ->label(__('bachelor_transfer_applications.columns.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('gender')
                    ->label(__('bachelor_transfer_applications.filters.gender'))
                    ->options(__('bachelor_transfer_applications.options.gender')),

                SelectFilter::make('status')
                    ->label(__('bachelor_transfer_applications.filters.status'))
                    ->options(__('bachelor_transfer_applications.options.status')),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('bachelor_transfer_applications.actions.edit'))
                    ->icon('heroicon-o-pencil-square'),

                DeleteAction::make()
                    ->label(__('bachelor_transfer_applications.actions.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->modalHeading(__('bachelor_transfer_applications.modal.delete_heading'))
                    ->modalDescription(__('bachelor_transfer_applications.modal.delete_description'))
                    ->modalSubmitActionLabel(__('bachelor_transfer_applications.modal.delete_submit')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('bachelor_transfer_applications.actions.delete_selected')),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('bachelor_transfer_applications.empty.heading'))
            ->emptyStateDescription(__('bachelor_transfer_applications.empty.description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBachelorTransferApplications::route('/'),
            'create' => CreateBachelorTransferApplication::route('/create'),
            'edit' => EditBachelorTransferApplication::route('/{record}/edit'),
        ];
    }
}
