<?php

namespace Chanthoeun\FilamentDocumentBuilder\Resources;

use BackedEnum;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Chanthoeun\FilamentDocumentBuilder\DocumentBuilderPlugin;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Pages;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Schemas\DocumentTemplateForm;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Tables\DocumentTemplateTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DocumentTemplateResource extends Resource
{
    public static function getModelLabel(): string
    {
        return __('filament-document-builder::document-builder.labels.document_template');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-document-builder::document-builder.labels.document_templates');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.document_templates');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.form_builder');
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    protected static ?string $model = DocumentTemplate::class;

    public static function shouldRegisterNavigation(): bool
    {
        return static::currentUserCanAccessResource();
    }

    public static function canAccess(): bool
    {
        return static::currentUserCanAccessResource();
    }

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        /** @var DocumentBuilderPlugin $plugin */
        $plugin = filament('filament-document-builder');

        return $plugin->getNavigationIcon();
    }

    public static function form(Schema $schema): Schema
    {
        return DocumentTemplateForm::schema($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentTemplateTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentTemplates::route('/'),
            'create' => Pages\CreateDocumentTemplate::route('/create'),
            'edit' => Pages\EditDocumentTemplate::route('/{record}/edit'),
        ];
    }

    protected static function currentUserCanAccessResource(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $permissions = FilamentShield::getResourcePermissions(static::class) ?? [];

        if ($permissions === []) {
            return static::canViewAny();
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
