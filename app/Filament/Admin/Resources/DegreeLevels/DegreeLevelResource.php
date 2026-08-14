<?php

namespace App\Filament\Admin\Resources\DegreeLevels;

use App\Filament\Admin\Resources\DegreeLevels\Pages\CreateDegreeLevel;
use App\Filament\Admin\Resources\DegreeLevels\Pages\EditDegreeLevel;
use App\Filament\Admin\Resources\DegreeLevels\Pages\ListDegreeLevels;
use App\Filament\Admin\Resources\DegreeLevels\Schemas\DegreeLevelForm;
use App\Filament\Admin\Resources\DegreeLevels\Tables\DegreeLevelsTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\DegreeLevel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class DegreeLevelResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = DegreeLevel::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('degree_levels.navigation_label');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return __('navigation.groups.settings');
    }

    public static function getModelLabel(): string
    {
        return __('degree_levels.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('degree_levels.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return DegreeLevelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DegreeLevelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDegreeLevels::route('/'),
            'create' => CreateDegreeLevel::route('/create'),
            'edit' => EditDegreeLevel::route('/{record}/edit'),
        ];
    }
}
