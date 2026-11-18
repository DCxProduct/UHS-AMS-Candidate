<?php

namespace App\Filament\Admin\Resources\NewStudentAccounts;

use App\Filament\Admin\Resources\NewStudentAccounts\Pages\CreateNewStudentAccount;
use App\Filament\Admin\Resources\NewStudentAccounts\Pages\EditNewStudentAccount;
use App\Filament\Admin\Resources\NewStudentAccounts\Pages\ListNewStudentAccounts;
use App\Filament\Admin\Resources\NewStudentAccounts\Schemas\NewStudentAccountForm;
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
        if (in_array($action, ['delete', 'deleteAny', 'forceDelete', 'forceDeleteAny'], true)
            || auth()->user()?->registration_type !== 'admin') {
            return Response::deny();
        }

        if ($record instanceof NewStudentAccount) {
            if ($record->archived_at !== null && in_array($action, ['activateAccount', 'deactivateAccount', 'archiveAccount'], true)) {
                return Response::deny();
            }

            if ($record->archived_at === null && $action === 'restoreAccount') {
                return Response::deny();
            }
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

    public static function table(Table $table): Table
    {
        return NewStudentAccountsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewStudentAccounts::route('/'),
            'create' => CreateNewStudentAccount::route('/create'),
            'edit' => EditNewStudentAccount::route('/{record}/edit'),
        ];
    }
}
