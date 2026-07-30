<?php

namespace App\Filament\Admin\Resources\Users;

use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Resources\Users\Schemas\UserForm;
use App\Filament\Admin\Resources\Users\Tables\UsersTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\SystemUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class UserResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = SystemUser::class;

    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 70;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'users';

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
        return 4;
    }

    public static function getModelLabel(): string
    {
        return __('users.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('users.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            return $query->where(function (Builder $query): void {
                $query
                    ->whereRaw("LOWER(COALESCE(roles::text, '')) LIKE ?", ['%"candidate"%'])
                    ->orWhereRaw("LOWER(COALESCE(roles::text, '')) LIKE ?", ['%"student"%']);
            });
        }

        return $query->where(function (Builder $query): void {
            $query
                ->whereRaw("LOWER(COALESCE(CAST(roles AS CHAR), '')) LIKE ?", ['%"candidate"%'])
                ->orWhereRaw("LOWER(COALESCE(CAST(roles AS CHAR), '')) LIKE ?", ['%"student"%']);
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
