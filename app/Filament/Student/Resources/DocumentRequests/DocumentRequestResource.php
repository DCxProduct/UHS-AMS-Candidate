<?php

namespace App\Filament\Student\Resources\DocumentRequests;

use App\Filament\Student\Resources\DocumentRequests\Pages\CreateDocumentRequest;
use App\Filament\Student\Resources\DocumentRequests\Pages\EditDocumentRequest;
use App\Filament\Student\Resources\DocumentRequests\Pages\ListDocumentRequests;
use App\Models\DocumentRequest;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden as HiddenField;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class DocumentRequestResource extends Resource
{
    protected static ?string $model = DocumentRequest::class;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'request-documents';

    protected static bool $shouldRegisterNavigation = true;

    public static function getNavigationLabel(): string
    {
        return __('app.forms_nav.request-document');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.student_application');
    }

    public static function getModelLabel(): string
    {
        return __('app.forms_nav.request-document');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.forms_nav.request-document');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-document-text';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canEdit($record): bool
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

        if (DatabaseSchema::hasColumn('document_requests', 'created_by')) {
            return $query->where('created_by', $userId);
        }

        if (DatabaseSchema::hasColumn('document_requests', 'user_id')) {
            return $query->where('user_id', $userId);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.requested_document_type'))
                    ->schema([
                        Select::make('request_type')
                            ->label(__('app.forms_nav.request-document'))
                            ->options([
                                'academic_confirmation' => __('app.document_types.academic_confirmation'),
                                'academic_transcript' => __('app.document_types.academic_transcript'),
                                'certificate_of_completion' => __('app.document_types.certificate_of_completion'),
                                'diploma' => __('app.document_types.diploma'),
                                'bachelor_certificate' => __('app.document_types.bachelor_certificate'),
                                'master_certificate' => __('app.document_types.master_certificate'),
                                'other' => __('app.document_types.other'),
                            ])
                            ->native(false)
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $set('request_documents', $state ? [$state] : []);
                            })
                            ->required()
                            ->columnSpan(6),

                        Select::make('faculty')
                            ->label(__('app.faculty'))
                            ->options([
                                'medicine' => __('app.faculties.medicine'),
                                'pharmacy' => __('app.faculties.pharmacy'),
                                'dentistry' => __('app.faculties.dentistry'),
                                'public_health' => __('app.faculties.public_health'),
                                'tsmc' => __('app.faculties.tsmc'),
                                'foundation_year' => __('app.faculties.foundation_year'),
                            ])
                            ->native(false)
                            ->searchable()
                            ->live()
                            ->required()
                            ->columnSpan(6),
                    ])
                    ->columns(12)
                    ->columnSpanFull(),

                Section::make(__('app.request_form'))
                    ->visible(fn (Get $get): bool => filled($get('request_type')))
                    ->schema([
                        HiddenField::make('photo'),
                        HiddenField::make('request_documents')->default([]),

                        HiddenField::make('student_id'),
                        HiddenField::make('name_kh'),
                        HiddenField::make('family_name_kh'),
                        HiddenField::make('first_name_kh'),
                        HiddenField::make('family_name_en'),
                        HiddenField::make('first_name_en'),
                        HiddenField::make('gender'),
                        HiddenField::make('student_type'),

                        HiddenField::make('birth_date'),
                        HiddenField::make('birth_place'),
                        HiddenField::make('current_address'),
                        HiddenField::make('village'),
                        HiddenField::make('province'),
                        HiddenField::make('phone'),
                        HiddenField::make('email'),

                        HiddenField::make('current_status'),
                        HiddenField::make('current_studying')->default(false),
                        HiddenField::make('current_year'),
                        HiddenField::make('academic_year'),

                        HiddenField::make('promotion'),
                        HiddenField::make('major'),
                        HiddenField::make('year_enrollment'),
                        HiddenField::make('graduation_year'),

                        HiddenField::make('languages')->default([]),
                        HiddenField::make('khmer_copies')->default(0),
                        HiddenField::make('english_copies')->default(0),
                        HiddenField::make('french_copies')->default(0),
                        HiddenField::make('sealed_envelope_copies')->default(0),
                        HiddenField::make('stamp_copies')->default(0),

                        HiddenField::make('diploma_original')->default(false),
                        HiddenField::make('diploma_copy')->default(false),
                        HiddenField::make('diploma_copy_number'),

                        HiddenField::make('received_day'),
                        HiddenField::make('received_month'),
                        HiddenField::make('received_year'),

                        HiddenField::make('request_day'),
                        HiddenField::make('request_month'),
                        HiddenField::make('request_year'),

                        HiddenField::make('applicant_signature_name'),
                        HiddenField::make('office_permission_no'),
                        HiddenField::make('verified_signature')->default(false),
                        HiddenField::make('office_process'),

                        HiddenField::make('purpose'),
                        HiddenField::make('is_confirmed')->default(false),
                        HiddenField::make('office_note'),
                        HiddenField::make('status')->default('pending'),

                        HiddenField::make('pdf_file'),

                        SchemaView::make('filament.student.document-requests.pdf-form')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student_id')
                    ->label(__('app.student_id'))
                    ->searchable(),

                TextColumn::make('name_kh')
                    ->label(__('app.student_name'))
                    ->searchable(),

                TextColumn::make('request_type')
                    ->label(__('app.request_type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'academic_confirmation' => __('app.document_types.academic_confirmation'),
                        'academic_transcript' => __('app.document_types.academic_transcript'),
                        'certificate_of_completion' => __('app.document_types.certificate_of_completion'),
                        'diploma' => __('app.document_types.diploma'),
                        'bachelor_certificate' => __('app.document_types.bachelor_certificate'),
                        'master_certificate' => __('app.document_types.master_certificate'),
                        'other' => __('app.document_types.other'),
                        default => '-',
                    })
                    ->searchable(),

                TextColumn::make('status')
                    ->label(__('app.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending' => __('app.pending'),
                        'approved' => __('app.approved'),
                        'rejected' => __('app.rejected'),
                        default => '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('pdf_file')
                    ->label(__('app.pdf'))
                    ->formatStateUsing(fn ($state): string => $state ? __('app.view_pdf') : '-')
                    ->url(fn ($record): ?string => $record->pdf_file ? asset('storage/' . $record->pdf_file) : null)
                    ->openUrlInNewTab(),

                TextColumn::make('created_at')
                    ->label(__('app.requested_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentRequests::route('/'),
            'create' => CreateDocumentRequest::route('/create'),
            'edit' => EditDocumentRequest::route('/{record}/edit'),
        ];
    }
}
