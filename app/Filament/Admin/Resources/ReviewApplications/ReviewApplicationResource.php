<?php

namespace App\Filament\Admin\Resources\ReviewApplications;

use App\Filament\Admin\Resources\ReviewApplications\Pages;
use App\Filament\Admin\Resources\ReviewApplications\Tables\ReviewApplicationsTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\ReviewApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Schema as DbSchema;
use UnitEnum;

class ReviewApplicationResource extends Resource
{
    use AdminOnly;

    public const HIDDEN_FLAG = 'hidden_from_review_applications';

    protected static ?string $model = ReviewApplication::class;

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
                'accepted',
                'approved',
            ])
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('data->candidate_status')
                    ->orWhere('data->candidate_status', '')
                    ->orWhere('data->candidate_status', 'pending');
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('customForm', function (Builder $query): void {
                        $query->where('requires_payment', false);
                    })
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->whereHas('customForm', function (Builder $query): void {
                                $query->where('requires_payment', true);
                            })
                            ->whereExists(function (QueryBuilder $subQuery): void {
                                $subQuery->selectRaw('1')
                                    ->from('payments')
                                    ->whereColumn('payments.form_id', 'custom_form_entries.custom_form_id')
                                    ->where(fn (QueryBuilder $matchQuery): QueryBuilder => static::applyPaymentOwnerMatch($matchQuery))
                                    ->where('payments.status_payt', 'paid');
                            });
                    });
            })
            ->where(function (Builder $query): void {
                $query->whereNull('data->' . static::HIDDEN_FLAG)
                    ->orWhere('data->' . static::HIDDEN_FLAG, false)
                    ->orWhere('data->' . static::HIDDEN_FLAG, 'false')
                    ->orWhere('data->' . static::HIDDEN_FLAG, 0)
                    ->orWhere('data->' . static::HIDDEN_FLAG, '0');
            })
            ->latest('id');
    }

    protected static function applyPaymentOwnerMatch(QueryBuilder $query): QueryBuilder
    {
        $ownerColumns = collect(['created_by', 'user_id', 'created_by_id'])
            ->filter(fn (string $column): bool => DbSchema::hasColumn('custom_form_entries', $column))
            ->values();

        if ($ownerColumns->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $firstColumn = $ownerColumns->shift();

        $query->whereColumn('payments.users_id', "custom_form_entries.{$firstColumn}");

        foreach ($ownerColumns as $column) {
            $query->orWhereColumn('payments.users_id', "custom_form_entries.{$column}");
        }

        return $query;
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
