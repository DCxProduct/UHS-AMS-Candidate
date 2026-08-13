<?php

namespace App\Filament\Admin\Resources\CandidateSubmitPopupSettings;

use App\Filament\Admin\Resources\CandidateSubmitPopupSettings\Pages\EditCandidateSubmitPopupSetting;
use App\Filament\Admin\Resources\CandidateSubmitPopupSettings\Pages\ListCandidateSubmitPopupSettings;
use App\Filament\Admin\Resources\CandidateSubmitPopupSettings\Schemas\CandidateSubmitPopupSettingForm;
use App\Filament\Concerns\AdminOnly;
use App\Models\CandidateSubmitPopupSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CandidateSubmitPopupSettingResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = CandidateSubmitPopupSetting::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?int $navigationSort = 64;

    public static function getNavigationLabel(): string
    {
        return __('candidate_submit_popup_settings.navigation_label');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return __('navigation.groups.settings');
    }

    public static function getNavigationSort(): ?int
    {
        return 7;
    }

    public static function getModelLabel(): string
    {
        return __('candidate_submit_popup_settings.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('candidate_submit_popup_settings.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return CandidateSubmitPopupSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([])->recordActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCandidateSubmitPopupSettings::route('/'),
            'edit' => EditCandidateSubmitPopupSetting::route('/{record}/edit'),
        ];
    }
}
