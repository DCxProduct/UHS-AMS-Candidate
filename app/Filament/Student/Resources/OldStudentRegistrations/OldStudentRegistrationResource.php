<?php

namespace App\Filament\Student\Resources\OldStudentRegistrations;

use App\Filament\Student\Resources\OldStudentRegistrations\Pages\CreateOldStudentRegistration;
use App\Filament\Student\Resources\OldStudentRegistrations\Pages\EditOldStudentRegistration;
use App\Filament\Student\Resources\OldStudentRegistrations\Pages\ListOldStudentRegistrations;
use App\Models\OldStudentRegistration;
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

class OldStudentRegistrationResource extends Resource
{
    protected static ?string $model = OldStudentRegistration::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'old-student-registrations';

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $recordTitleAttribute = 'khmer_name';

    public static function getNavigationLabel(): string
    {
        return __('old_student_registrations.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.student_application');
    }

    public static function getModelLabel(): string
    {
        return __('old_student_registrations.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('old_student_registrations.plural_model_label');
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

        if (! DatabaseSchema::hasTable('old_student_registrations')) {
            return $query->whereRaw('1 = 0');
        }

        if (DatabaseSchema::hasColumn('old_student_registrations', 'created_by')) {
            return $query->where('created_by', $userId);
        }

        if (DatabaseSchema::hasColumn('old_student_registrations', 'user_id')) {
            return $query->where('user_id', $userId);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('old_student_registrations.form.title'))
                    ->schema([
                        HiddenField::make('registration_no'),
                        HiddenField::make('user_id'),

                        HiddenField::make('student_id'),
                        HiddenField::make('khmer_name'),
                        HiddenField::make('family_name'),
                        HiddenField::make('first_name'),
                        HiddenField::make('sex'),
                        HiddenField::make('date_of_birth'),
                        HiddenField::make('nationality')
                            ->default(fn (): string => __('old_student_registrations.defaults.nationality')),
                        HiddenField::make('religion'),
                        HiddenField::make('place_of_birth'),
                        HiddenField::make('marital_status'),

                        HiddenField::make('current_job'),
                        HiddenField::make('institution'),
                        HiddenField::make('workshop_course'),
                        HiddenField::make('student_type'),

                        HiddenField::make('permanent_no'),
                        HiddenField::make('permanent_street'),
                        HiddenField::make('permanent_sangkat'),
                        HiddenField::make('permanent_khan_district'),
                        HiddenField::make('permanent_city_state_country'),

                        HiddenField::make('current_no'),
                        HiddenField::make('current_street'),
                        HiddenField::make('current_sangkat'),
                        HiddenField::make('current_khan_district'),
                        HiddenField::make('current_city_country'),

                        HiddenField::make('phone_no'),
                        HiddenField::make('email'),

                        HiddenField::make('father_name'),
                        HiddenField::make('father_year_of_birth'),
                        HiddenField::make('father_occupation'),

                        HiddenField::make('mother_name'),
                        HiddenField::make('mother_year_of_birth'),
                        HiddenField::make('mother_occupation'),

                        HiddenField::make('contact_person'),
                        HiddenField::make('contact_no'),

                        HiddenField::make('photo_path'),
                        HiddenField::make('signature_path'),
                        HiddenField::make('generated_pdf_path'),

                        HiddenField::make('status')->default('draft'),
                        HiddenField::make('submitted_at'),

                        HiddenField::make('created_by')
                            ->default(fn (): ?int => auth()->id()),

                        HiddenField::make('updated_by')
                            ->default(fn (): ?int => auth()->id()),

                        HiddenField::make('extra_data'),

                        SchemaView::make('filament.student.old-student-registrations.pdf-form')
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
                    ->label(__('old_student_registrations.columns.no'))
                    ->rowIndex()
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('registration_no')
                    ->label(__('old_student_registrations.columns.registration_no'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('student_id')
                    ->label(__('old_student_registrations.columns.student_id'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('khmer_name')
                    ->label(__('old_student_registrations.columns.khmer_name'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('family_name')
                    ->label(__('old_student_registrations.columns.family_name'))
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('first_name')
                    ->label(__('old_student_registrations.columns.first_name'))
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('sex')
                    ->label(__('old_student_registrations.columns.sex'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::translateOption('sex', $state))
                    ->color(fn (?string $state): string => match ($state) {
                        'male', 'ប្រុស' => 'info',
                        'female', 'ស្រី' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('student_type')
                    ->label(__('old_student_registrations.columns.student_type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::translateOption('student_type', $state))
                    ->color(fn (?string $state): string => match ($state) {
                        'regular', 'fee', 'បង់ថ្លៃ' => 'success',
                        'scholarship', 'អាហារូបករណ៍' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('phone_no')
                    ->label(__('old_student_registrations.columns.phone_no'))
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('email')
                    ->label(__('old_student_registrations.columns.email'))
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label(__('old_student_registrations.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::translateOption('status', $state))
                    ->color(fn (?string $state): string => match ($state) {
                        'submitted' => 'info',
                        'reviewing' => 'primary',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('old_student_registrations.columns.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('old_student_registrations.columns.updated_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('sex')
                    ->label(__('old_student_registrations.filters.sex'))
                    ->options(__('old_student_registrations.options.sex')),

                SelectFilter::make('student_type')
                    ->label(__('old_student_registrations.filters.student_type'))
                    ->options(__('old_student_registrations.options.student_type')),

                SelectFilter::make('status')
                    ->label(__('old_student_registrations.filters.status'))
                    ->options(__('old_student_registrations.options.status')),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('old_student_registrations.actions.edit'))
                    ->icon('heroicon-o-pencil-square'),

                DeleteAction::make()
                    ->label(__('old_student_registrations.actions.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->modalHeading(__('old_student_registrations.modal.delete_heading'))
                    ->modalDescription(__('old_student_registrations.modal.delete_description'))
                    ->modalSubmitActionLabel(__('old_student_registrations.modal.delete_submit')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('old_student_registrations.actions.delete_selected')),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('old_student_registrations.empty_state.heading'))
            ->emptyStateDescription(__('old_student_registrations.empty_state.description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOldStudentRegistrations::route('/'),
            'create' => CreateOldStudentRegistration::route('/create'),
            'edit' => EditOldStudentRegistration::route('/{record}/edit'),
        ];
    }

    private static function translateOption(string $group, ?string $state): string
    {
        if (blank($state)) {
            return '-';
        }

        $key = match ($group) {
            'sex' => match ($state) {
                'ប្រុស' => 'male',
                'ស្រី' => 'female',
                default => $state,
            },
            'student_type' => match ($state) {
                'បង់ថ្លៃ', 'fee' => 'regular',
                'អាហារូបករណ៍' => 'scholarship',
                default => $state,
            },
            default => $state,
        };

        $translationKey = "old_student_registrations.options.{$group}.{$key}";

        return __($translationKey) === $translationKey ? (string) $state : __($translationKey);
    }
}
