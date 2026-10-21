<?php

namespace App\Filament\Admin\Resources\CandidateSubmitPopupSettings;

use App\Filament\Admin\Resources\CandidateSubmitPopupSettings\Pages\CreateCandidateSubmitPopupSetting;
use App\Filament\Admin\Resources\CandidateSubmitPopupSettings\Pages\EditCandidateSubmitPopupSetting;
use App\Filament\Admin\Resources\CandidateSubmitPopupSettings\Pages\ListCandidateSubmitPopupSettings;
use App\Filament\Admin\Resources\CandidateSubmitPopupSettings\Schemas\CandidateSubmitPopupSettingForm;
use App\Filament\Concerns\AdminOnly;
use App\Models\CandidateSubmitPopupSetting;
use App\Support\LocalizedDate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CandidateSubmitPopupSettingResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = CandidateSubmitPopupSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?int $navigationSort = 7;

    public static function getNavigationLabel(): string
    {
        return __('candidate_submit_popup_settings.navigation_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
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
        return $table
            ->modifyQueryUsing(fn ($query) => $query->whereNotNull('custom_form_id'))
            ->columns([
                TextColumn::make('customForm.display_name')
                    ->label(__('candidate_submit_popup_settings.fields.form'))
                    ->searchable(query: fn ($query, string $search) => $query->whereHas(
                        'customForm',
                        fn ($forms) => $forms->where('name', 'like', "%{$search}%")
                    )),
                IconColumn::make('is_active')
                    ->label(__('candidate_submit_popup_settings.fields.active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('candidate_submit_popup_settings.fields.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),
                TextColumn::make('updated_at')
                    ->label(__('candidate_submit_popup_settings.fields.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCandidateSubmitPopupSettings::route('/'),
            'create' => CreateCandidateSubmitPopupSetting::route('/create'),
            'edit' => EditCandidateSubmitPopupSetting::route('/{record}/edit'),
        ];
    }
}
