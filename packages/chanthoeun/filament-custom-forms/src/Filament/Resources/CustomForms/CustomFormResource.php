<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms;

use BackedEnum;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Chanthoeun\FilamentCustomForms\CustomFormPlugin;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Pages;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Schemas\CustomFormForm;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Tables\CustomFormsTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CustomFormResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('navigation.custom_forms');
    }

    public static function getNavigationSort(): ?int
    {
        return 40;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.form_builder');
    }
    public static function getModel(): string
    {
        return CustomFormPlugin::get()->getFormModel();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::currentUserCanAccessResource();
    }

    public static function canAccess(): bool
    {
        return static::currentUserCanAccessResource();
    }

    public static function getModelLabel(): string
    {
        return __('filament-custom-forms::fcf.form.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-custom-forms::fcf.form.plural');
    }

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return CustomFormPlugin::get()->getNavigationFormIcon();
    }

    public static function form(Schema $schema): Schema
    {
        return CustomFormForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomFormsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\FieldsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomForms::route('/'),
            'create' => Pages\CreateCustomForm::route('/create'),
            'edit' => Pages\EditCustomForm::route('/{record}/edit'),
        ];
    }

    protected static function currentUserCanAccessResource(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('ViewAny:' . class_basename(static::getModel()));
    }
}
