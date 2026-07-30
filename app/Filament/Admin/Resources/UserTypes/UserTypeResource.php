<?php

namespace App\Filament\Admin\Resources\UserTypes;

use App\Filament\Admin\Resources\UserTypes\Pages\CreateUserType;
use App\Filament\Admin\Resources\UserTypes\Pages\EditUserType;
use App\Filament\Admin\Resources\UserTypes\Pages\ListUserTypes;
use App\Filament\Admin\Resources\UserTypes\Schemas\UserTypeForm;
use App\Filament\Admin\Resources\UserTypes\Tables\UserTypesTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\UserType;
use App\Support\UserTypeOptions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UserTypeResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = UserType::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user';

    protected static ?int $navigationSort = 61;

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getNavigationLabel(): string
    {
        return __('user_types.navigation_label');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return __('navigation.groups.settings');
    }

    public static function getModelLabel(): string
    {
        return __('user_types.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('user_types.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return UserTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserTypesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return UserTypeOptions::allQuery();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserTypes::route('/'),
            'create' => CreateUserType::route('/create'),
            'edit' => EditUserType::route('/{record}/edit'),
        ];
    }
}
