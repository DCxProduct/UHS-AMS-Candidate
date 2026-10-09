<?php

namespace App\Filament\Admin\Resources\SmsLogs;

use App\Filament\Admin\Resources\SmsLogs\Pages\ListSmsLogs;
use App\Filament\Admin\Resources\SmsLogs\Tables\SmsLogsTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\SmsLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only history of every SMS handed to PlasGate.
 */
class SmsLogResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = SmsLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    protected static ?int $navigationSort = 93;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('navigation.groups.form_builder');
    }

    public static function getNavigationLabel(): string
    {
        return __('sms_logs.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('sms_logs.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('sms_logs.plural_model_label');
    }

    public static function table(Table $table): Table
    {
        return SmsLogsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSmsLogs::route('/'),
        ];
    }
}
