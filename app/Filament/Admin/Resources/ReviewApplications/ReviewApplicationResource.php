<?php

namespace App\Filament\Admin\Resources\ReviewApplications;

use App\Filament\Admin\Resources\ReviewApplications\Pages;
use App\Filament\Admin\Resources\ReviewApplications\Tables\ReviewApplicationsTable;
use App\Filament\Concerns\AdminOnly;
use BackedEnum;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ReviewApplicationResource extends Resource
{
    use AdminOnly;
    public static function getModel(): string
    {
        return CustomFormEntry::class;
    }

    protected static ?string $slug = 'review-applications';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('review_applications.navigation_label');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return __('review_applications.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('review_applications.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('review_applications.plural_model_label');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'creator',
                'customForm',
            ])
            ->whereHas('customForm', function (Builder $query): void {
                $query->where('slug', 'enrollment');
            })
            ->latest('id');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return ReviewApplicationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviewApplications::route('/'),
        ];
    }
}
