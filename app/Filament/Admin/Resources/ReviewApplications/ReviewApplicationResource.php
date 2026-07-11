<?php

namespace App\Filament\Admin\Resources\ReviewApplications;

use App\Filament\Admin\Resources\ReviewApplications\Pages;
use App\Filament\Admin\Resources\ReviewApplications\Tables\ReviewApplicationsTable;
use App\Filament\Concerns\AdminOnly;
use BackedEnum;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
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

    protected static ?string $slug = 'candidate-lists';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('navigation.review_applications');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.candidates');
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
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
                $query
                    ->where(function (Builder $query): void {
                        $query->where('menu_placement', 'sidebar')
                            ->where('is_active', true)
                            ->where('slug', '!=', 'profile');
                    })
                    ->orWhere(function (Builder $query): void {
                        $query->where('menu_placement', 'sub_item')
                            ->where('is_active', true)
                            ->whereHas('parentForm', function (Builder $query): void {
                                $query->where('menu_placement', 'sidebar')
                                    ->where('is_active', true)
                                    ->where('slug', '!=', 'profile');
                            });
                    });
            })
            ->whereIn('review_status', [
                'passed',
                'accepted',
                'approved',
            ])
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
