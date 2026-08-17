<?php

namespace App\Filament\Admin\Resources\SystemUsers;

use App\Filament\Admin\Resources\SystemUsers\Pages\CreateSystemUser;
use App\Filament\Admin\Resources\SystemUsers\Pages\EditSystemUser;
use App\Filament\Admin\Resources\SystemUsers\Pages\ListSystemUsers;
use App\Filament\Admin\Resources\SystemUsers\Schemas\SystemUserForm;
use App\Filament\Admin\Resources\SystemUsers\Tables\SystemUsersTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\SystemUser;
use App\Support\UserTypeOptions;
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

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 71;

    protected static ?string $recordTitleAttribute = 'username';

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
        return 3;
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
        $candidateRoles = UserTypeOptions::candidateManagedRoleKeys();

        if ($candidateRoles === []) {
            return $query;
        }

        if ($driver === 'pgsql') {
            return $query->where(function (Builder $query) use ($candidateRoles): void {
                $query
                    ->where(function (Builder $query) use ($candidateRoles): void {
                        foreach ($candidateRoles as $role) {
                            $query->whereRaw("LOWER(COALESCE(roles::text, '')) NOT LIKE ?", ['%"' . $role . '"%']);
                        }
                    })
                    ->orWhere(function (Builder $query): void {
                        $query->whereExists(function ($subQuery): void {
                            $subQuery
                                ->selectRaw('1')
                                ->from('users')
                                ->whereNull('users.deleted_at')
                                ->where('users.registration_type', 'admin')
                                ->where(function ($match): void {
                                    $match
                                        ->whereColumn('users.username', 'system_users.username')
                                        ->orWhereColumn('users.email', 'system_users.email')
                                        ->orWhereColumn('users.phone', 'system_users.phone');
                                });
                        });
                    });
            });
        }

        return $query->where(function (Builder $query) use ($candidateRoles): void {
            $query
                ->where(function (Builder $query) use ($candidateRoles): void {
                    foreach ($candidateRoles as $role) {
                        $query->whereRaw("LOWER(COALESCE(CAST(roles AS CHAR), '')) NOT LIKE ?", ['%"' . $role . '"%']);
                    }
                })
                ->orWhere(function (Builder $query): void {
                    $query->whereExists(function ($subQuery): void {
                        $subQuery
                            ->selectRaw('1')
                            ->from('users')
                            ->whereNull('users.deleted_at')
                            ->where('users.registration_type', 'admin')
                            ->where(function ($match): void {
                                $match
                                    ->whereColumn('users.username', 'system_users.username')
                                    ->orWhereColumn('users.email', 'system_users.email')
                                    ->orWhereColumn('users.phone', 'system_users.phone');
                            });
                    });
                });
        });
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
