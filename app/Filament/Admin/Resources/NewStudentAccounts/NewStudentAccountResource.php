<?php

namespace App\Filament\Admin\Resources\NewStudentAccounts;

use App\Filament\Admin\Resources\NewStudentAccounts\Pages\CreateNewStudentAccount;
use App\Filament\Admin\Resources\NewStudentAccounts\Pages\EditNewStudentAccount;
use App\Filament\Admin\Resources\NewStudentAccounts\Pages\ListNewStudentAccounts;
use App\Filament\Admin\Resources\NewStudentAccounts\Pages\ViewNewStudentAccount;
use App\Filament\Admin\Resources\NewStudentAccounts\Schemas\NewStudentAccountForm;
use App\Filament\Admin\Resources\NewStudentAccounts\Schemas\NewStudentAccountInfolist;
use App\Filament\Admin\Resources\NewStudentAccounts\Tables\NewStudentAccountsTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\NewStudentAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class NewStudentAccountResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = NewStudentAccount::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $slug = 'new-student-accounts';

    protected static ?string $recordTitleAttribute = 'student_id';

    protected static ?int $navigationSort = 12;

    protected static function currentUserCanAccessResource(): bool
    {
        return auth()->user()?->registration_type === 'admin' && static::canViewAny();
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        if (auth()->user()?->registration_type !== 'admin') {
            return Response::deny();
        }

        return parent::getAuthorizationResponse($action, $record);
    }

    public static function getNavigationLabel(): string
    {
        return __('new_student_accounts.navigation_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('navigation.groups.settings');
    }

    public static function getModelLabel(): string
    {
        return __('new_student_accounts.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('new_student_accounts.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return NewStudentAccountForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NewStudentAccountInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewStudentAccountsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewStudentAccounts::route('/'),
            'create' => CreateNewStudentAccount::route('/create'),
            'view' => ViewNewStudentAccount::route('/{record}'),
            'edit' => EditNewStudentAccount::route('/{record}/edit'),
        ];
    }
}
