<?php

namespace App\Filament\Admin\Resources\CandidateTypes;

use App\Filament\Admin\Resources\CandidateTypes\Pages\CreateCandidateType;
use App\Filament\Admin\Resources\CandidateTypes\Pages\EditCandidateType;
use App\Filament\Admin\Resources\CandidateTypes\Pages\ListCandidateTypes;
use App\Filament\Admin\Resources\CandidateTypes\Schemas\CandidateTypeForm;
use App\Filament\Admin\Resources\CandidateTypes\Tables\CandidateTypesTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\CandidateType;
use App\Support\CandidateTypeOptions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CandidateTypeResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = CandidateType::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('candidate_types.navigation_label');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return __('navigation.groups.settings');
    }

    public static function getModelLabel(): string
    {
        return __('candidate_types.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('candidate_types.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return CandidateTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CandidateTypesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return CandidateTypeOptions::allQuery();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCandidateTypes::route('/'),
            'create' => CreateCandidateType::route('/create'),
            'edit' => EditCandidateType::route('/{record}/edit'),
        ];
    }
}
