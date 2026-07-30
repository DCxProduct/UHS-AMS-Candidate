<?php

namespace App\Filament\Admin\Resources\SystemUsers;

use App\Filament\Admin\Resources\SystemUsers\Pages\CreateSystemUser;
use App\Filament\Admin\Resources\SystemUsers\Pages\EditSystemUser;
use App\Filament\Admin\Resources\SystemUsers\Pages\ListSystemUsers;
use App\Filament\Admin\Resources\SystemUsers\Schemas\SystemUserForm;
use App\Filament\Admin\Resources\SystemUsers\Tables\SystemUsersTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\SystemUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SystemUserResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = SystemUser::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 71;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'system-users';

    public static function getNavigationLabel(): string
    {
        return __('navigation.system_users');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.settings');
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getModelLabel(): string
    {
        return __('system_users.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('system_users.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return SystemUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SystemUsersTable::configure($table);
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
            return $query
                ->whereRaw("LOWER(COALESCE(roles::text, '')) NOT LIKE ?", ['%"candidate"%'])
                ->whereRaw("LOWER(COALESCE(roles::text, '')) NOT LIKE ?", ['%"student"%']);
        }

        return $query
            ->whereRaw("LOWER(COALESCE(CAST(roles AS CHAR), '')) NOT LIKE ?", ['%"candidate"%'])
            ->whereRaw("LOWER(COALESCE(CAST(roles AS CHAR), '')) NOT LIKE ?", ['%"student"%']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSystemUsers::route('/'),
            'create' => CreateSystemUser::route('/create'),
            'edit' => EditSystemUser::route('/{record}/edit'),
        ];
    }
}
