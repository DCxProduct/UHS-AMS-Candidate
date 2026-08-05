<?php

namespace App\Filament\Admin\Resources\CandidatePaymentLists;

use App\Filament\Admin\Resources\CandidatePaymentLists\Pages\ListCandidatePaymentLists;
use App\Filament\Admin\Resources\CandidatePaymentLists\Tables\CandidatePaymentListsTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\CandidatePaymentList;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Schema as DbSchema;
use UnitEnum;

class CandidatePaymentListResource extends Resource
{
    use AdminOnly;

    public const HIDDEN_FLAG = 'hidden_from_candidate_payment_lists';

    protected static ?string $model = CandidatePaymentList::class;

    protected static ?string $slug = 'candidate-payment-lists';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('candidate_payment_lists.navigation_label');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return __('navigation.groups.cashier');
    }

    public static function getModelLabel(): string
    {
        return __('candidate_payment_lists.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('candidate_payment_lists.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return CandidatePaymentListsTable::configure($table);
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
            ->where(function (Builder $query): void {
                $query
                    ->where('data->candidate_status', 'passed')
                    ->orWhereIn('review_status', ['approved', 'accepted', 'passed'])
                    ->orWhereIn('data->registration_status', ['approved', 'accepted', 'passed']);
            })
            ->where(function (Builder $query): void {
                $query->whereNull('data->' . static::HIDDEN_FLAG)
                    ->orWhere('data->' . static::HIDDEN_FLAG, false)
                    ->orWhere('data->' . static::HIDDEN_FLAG, 'false')
                    ->orWhere('data->' . static::HIDDEN_FLAG, 0)
                    ->orWhere('data->' . static::HIDDEN_FLAG, '0');
            })
            ->whereNotExists(function (QueryBuilder $subQuery): void {
                $subQuery->selectRaw('1')
                    ->from('payments')
                    ->whereColumn('payments.form_id', 'custom_form_entries.custom_form_id')
                    ->where(fn (QueryBuilder $matchQuery): QueryBuilder => static::applyPaymentOwnerMatch($matchQuery))
                    ->where('payments.status_payt', 'paid');
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCandidatePaymentLists::route('/'),
        ];
    }
}
