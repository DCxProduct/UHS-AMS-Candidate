<?php

namespace App\Filament\Admin\Resources\AuditLogs;

use App\Filament\Admin\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Admin\Resources\AuditLogs\Tables\AuditLogsTable;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('audit_logs.navigation_label');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return __('audit_logs.activity_navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('audit_logs.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('audit_logs.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return AuditLogsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        if (! static::auditLogsTableExists()) {
            abort(404);
        }

        return parent::getEloquentQuery()
            ->with('actor');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::auditLogsTableExists() && static::userCanViewAuditLogs();
    }

    public static function canAccess(): bool
    {
        return static::auditLogsTableExists() && static::userCanViewAuditLogs();
    }

    public static function canViewAny(): bool
    {
        return static::auditLogsTableExists() && static::userCanViewAuditLogs();
    }

    protected static function auditLogsTableExists(): bool
    {
        return SchemaFacade::hasTable((new AuditLog)->getTable());
    }

    protected static function userCanViewAuditLogs(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('ViewAny:AuditLog')
            || $user->can('View:AuditLog')
            || $user->can('ViewAny:AuditLogResource')
            || $user->can('View:AuditLogResource');
    }
}
