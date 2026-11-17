<?php

namespace App\Filament\Admin\Resources\CandidateLists;

use App\Filament\Admin\Resources\CandidateLists\Pages\CreateCandidateList;
use App\Filament\Admin\Resources\CandidateLists\Pages\EditCandidateList;
use App\Filament\Admin\Resources\CandidateLists\Pages\ListCandidateLists;
use App\Filament\Admin\Resources\CandidateLists\Schemas\CandidateListForm;
use App\Filament\Admin\Resources\CandidateLists\Tables\CandidateListsTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\CandidateList;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CandidateListResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = CandidateList::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'candidate-lists';

    public static function getNavigationLabel(): string
    {
        return __('navigation.users');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.settings');
    }

    public static function getNavigationSort(): ?int
    {
        return 11;
    }

    public static function getModelLabel(): string
    {
        return __('candidate_lists.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('candidate_lists.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return CandidateListForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CandidateListsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('registration_type', 'student');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCandidateLists::route('/'),
            'create' => CreateCandidateList::route('/create'),
            'edit' => EditCandidateList::route('/{record}/edit'),
        ];
    }
}
