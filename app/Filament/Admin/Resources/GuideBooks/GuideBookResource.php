<?php

namespace App\Filament\Admin\Resources\GuideBooks;

use App\Models\GuideBook;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class GuideBookResource extends Resource
{
    protected static ?string $model = GuideBook::class;

    protected static ?string $slug = 'manage-guide-books';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 55;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('guide_books.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('guide_books.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('guide_books.navigation');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('guide_books.singular'))->columns(2)->columnSpanFull()->schema([
                TextInput::make('title_en')->label(__('guide_books.title_en'))->required()->maxLength(255),
                TextInput::make('title_km')->label(__('guide_books.title_km'))->required()->maxLength(255),
                FileUpload::make('file_path')->label(__('guide_books.file'))
                    ->helperText(__('guide_books.file_help'))
                    ->disk('guide_books')->directory('files')->visibility('private')
                    ->acceptedFileTypes(GuideBook::MIME_TYPES)->maxSize(10240)
                    ->storeFileNamesIn('file_name')->previewable(false)
                    ->required(fn ($livewire, Get $get): bool => ! ($livewire instanceof Pages\EditGuideBook && $livewire->guideResetPending) || (bool) $get('is_published'))
                    ->columnSpanFull(),
                Toggle::make('is_published')->label(__('guide_books.published'))->default(false),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageGuideBook::route('/'),
            'legacy-create' => Pages\ManageGuideBook::route('/create'),
            'view' => Pages\ViewGuideBook::route('/{record}'),
            'edit' => Pages\EditGuideBook::route('/{record}/edit'),
        ];
    }
}
