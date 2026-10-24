<?php

namespace App\Filament\Admin\Resources\MessagingSettings;

use App\Filament\Admin\Resources\MessagingSettings\Pages\EditMessagingSetting;
use App\Filament\Admin\Resources\MessagingSettings\Pages\ListMessagingSettings;
use App\Filament\Admin\Resources\MessagingSettings\Schemas\MessagingSettingForm;
use App\Filament\Concerns\AdminOnly;
use App\Models\MessagingSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MessagingSettingResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = MessagingSetting::class;

    protected static ?string $slug = 'email-sms-settings';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 9;

    public static function getNavigationLabel(): string
    {
        return __('messaging_settings.navigation_label');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return __('navigation.groups.settings');
    }

    public static function getNavigationSort(): ?int
    {
        return 9;
    }

    public static function getModelLabel(): string
    {
        return __('messaging_settings.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('messaging_settings.resource_label');
    }

    public static function form(Schema $schema): Schema
    {
        return MessagingSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([])->recordActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMessagingSettings::route('/'),
            'edit' => EditMessagingSetting::route('/{record}/edit'),
        ];
    }
}
