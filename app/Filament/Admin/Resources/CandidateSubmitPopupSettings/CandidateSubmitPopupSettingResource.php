<?php

namespace App\Filament\Admin\Resources\CandidateSubmitPopupSettings;

use App\Filament\Admin\Resources\CandidateSubmitPopupSettings\Pages\CreateCandidateSubmitPopupSetting;
use App\Filament\Admin\Resources\CandidateSubmitPopupSettings\Pages\EditCandidateSubmitPopupSetting;
use App\Filament\Admin\Resources\CandidateSubmitPopupSettings\Pages\ListCandidateSubmitPopupSettings;
use App\Filament\Admin\Resources\CandidateSubmitPopupSettings\Schemas\CandidateSubmitPopupSettingForm;
use App\Filament\Concerns\AdminOnly;
use App\Models\CandidateSubmitPopupSetting;
use App\Support\CaseInsensitiveSearch;
use App\Support\LocalizedDate;
use BackedEnum;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CandidateSubmitPopupSettingResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = CandidateSubmitPopupSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?int $navigationSort = 8;

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
        return 8;
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
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchFormNames($query, $search)),
                IconColumn::make('is_active')
                    ->label(__('candidate_submit_popup_settings.fields.active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('candidate_submit_popup_settings.fields.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyDate($query, $query->getModel()->qualifyColumn('created_at'), $search)),
                TextColumn::make('updated_at')
                    ->label(__('candidate_submit_popup_settings.fields.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyDate($query, $query->getModel()->qualifyColumn('updated_at'), $search)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    private static function searchFormNames(Builder $query, string $search): Builder
    {
        $term = CaseInsensitiveSearch::normalize($search);
        $ids = CustomForm::query()->get(['id', 'name'])
            ->filter(fn (CustomForm $form): bool => str_contains(CaseInsensitiveSearch::normalize($form->display_name), $term))
            ->modelKeys();

        return $query->whereHas('customForm', fn (Builder $forms): Builder => $forms->where(function (Builder $forms) use ($search, $ids): void {
            CaseInsensitiveSearch::applyCast($forms, $forms->getModel()->qualifyColumn('name'), $search);
            $forms->orWhereKey($ids);
        }));
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
