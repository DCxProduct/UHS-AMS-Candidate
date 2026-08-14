<?php

namespace App\Filament\Admin\Resources\RoleTypes;

use App\Filament\Admin\Resources\RoleTypes\Pages\CreateRoleType;
use App\Filament\Admin\Resources\RoleTypes\Pages\EditRoleType;
use App\Filament\Admin\Resources\RoleTypes\Pages\ListRoleTypes;
use App\Filament\Admin\Resources\RoleTypes\Schemas\RoleTypeForm;
use App\Filament\Admin\Resources\RoleTypes\Tables\RoleTypesTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\RoleType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class RoleTypeResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = RoleType::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 59;

    public static function getNavigationLabel(): string
    {
        return __('role_types.navigation_label');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return __('navigation.groups.settings');
    }

    public static function getModelLabel(): string
    {
        return __('role_types.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('role_types.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return RoleTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoleTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoleTypes::route('/'),
            'create' => CreateRoleType::route('/create'),
            'edit' => EditRoleType::route('/{record}/edit'),
        ];
    }
}
