<?php

namespace App\Filament\Admin\Resources\CandidateLists;

use App\Filament\Admin\Resources\CandidateLists\Pages\CreateCandidateList;
use App\Filament\Admin\Resources\CandidateLists\Pages\EditCandidateList;
use App\Filament\Admin\Resources\CandidateLists\Pages\ListCandidateLists;
use App\Filament\Admin\Resources\CandidateLists\Schemas\CandidateListForm;
use App\Filament\Admin\Resources\CandidateLists\Tables\CandidateListsTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\CandidateList;
use App\Support\UserTypeOptions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class CandidateListResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = CandidateList::class;

    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 70;

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
        return 4;
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
        $query = parent::getEloquentQuery();
        $driver = DB::connection()->getDriverName();
        $candidateRoles = UserTypeOptions::candidateManagedRoleKeys();

        if ($candidateRoles === []) {
            return $query;
        }

        if ($driver === 'pgsql') {
            return $query->where(function (Builder $query) use ($candidateRoles): void {
                foreach ($candidateRoles as $role) {
                    $query->orWhereRaw("LOWER(COALESCE(roles::text, '')) LIKE ?", ['%"' . $role . '"%']);
                }
            })->whereNotExists(function ($subQuery): void {
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
        }

        return $query->where(function (Builder $query) use ($candidateRoles): void {
            foreach ($candidateRoles as $role) {
                $query->orWhereRaw("LOWER(COALESCE(CAST(roles AS CHAR), '')) LIKE ?", ['%"' . $role . '"%']);
            }
        })->whereNotExists(function ($subQuery): void {
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
