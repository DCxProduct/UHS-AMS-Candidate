<?php

namespace App\Filament\Admin\Resources\PaymentStatusLists;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\PaymentStatusLists\Pages\CreatePaymentStatusList;
use App\Filament\Admin\Resources\PaymentStatusLists\Pages\ListPaymentStatusLists;
use App\Filament\Admin\Resources\Payments\Schemas\PaymentForm;
use App\Filament\Admin\Resources\PaymentStatusLists\Tables\PaymentStatusListsTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\PaymentStatusList;
use App\Models\WorkflowNotification;
use BackedEnum;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Schema as DbSchema;
use UnitEnum;

class PaymentStatusListResource extends Resource
{
    use AdminOnly;

    public const HIDDEN_FLAG = 'hidden_from_payment_status_lists';

    protected static ?string $model = PaymentStatusList::class;

    protected static ?string $slug = 'payment-status-lists';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('payment_status_lists.navigation_label');
    }

    public static function getNavigationBadge(): ?string
    {
        return number_format(static::getEloquentQuery()->count());
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return __('navigation.groups.cashier');
    }

    public static function getModelLabel(): string
    {
        return __('payment_status_lists.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payment_status_lists.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentForm::configure($schema, true);
    }

    public static function table(Table $table): Table
    {
        return PaymentStatusListsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $configuredPaymentFormIds = WorkflowNotification::formIdsWithStage(WorkflowStageType::Payment);
        $assignedWorkflowFormIds = WorkflowNotification::assignedFormIds();
        $paymentFormsQuery = CustomForm::query()->where('slug', '!=', 'profile');

        if ($assignedWorkflowFormIds !== []) {
            $paymentFormsQuery->where(function (Builder $query) use ($configuredPaymentFormIds, $assignedWorkflowFormIds): void {
                $query->whereIn('id', $configuredPaymentFormIds)
                    ->orWhere(function (Builder $query) use ($assignedWorkflowFormIds): void {
                        $query
                            ->whereNotIn('id', $assignedWorkflowFormIds)
                            ->where('requires_payment', true);
                    });
            });
        } else {
            $paymentFormsQuery->where('requires_payment', true);
        }

        $paymentFormIds = $paymentFormsQuery->pluck('id')->all();

        $query = parent::getEloquentQuery()
            ->with([
                'creator',
                'customForm',
            ])
            ->whereHas('customForm', function (Builder $query): void {
                $query->where('is_active', true)
                    ->where('slug', '!=', 'profile');
            })
            ->whereIn('custom_form_id', $paymentFormIds)
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
            ->latest('id');

        if (! static::currentUserCanViewAllRecords()) {
            $userId = auth()->id();

            if (! $userId) {
                return $query->whereRaw('1 = 0');
            }

            $ownerColumns = static::existingOwnerColumns();

            if ($ownerColumns === []) {
                return $query->whereRaw('1 = 0');
            }

            $query->where(function (Builder $query) use ($ownerColumns, $userId): void {
                foreach ($ownerColumns as $ownerColumn) {
                    $query->orWhere($ownerColumn, $userId);
                }
            });
        }

        return $query;
    }

    public static function getUnpaidEloquentQuery(): Builder
    {
        return static::getEloquentQuery()
            ->whereNotExists(function (QueryBuilder $subQuery): void {
                $subQuery->selectRaw('1')
                    ->from('payments')
                    ->where(fn (QueryBuilder $matchQuery): QueryBuilder => static::applyPaidPaymentMatch($matchQuery))
                    ->where('payments.status_payt', 'paid');
            });
    }

    protected static function applyPaymentOwnerMatch(QueryBuilder $query): QueryBuilder
    {
        $ownerColumns = collect(static::existingOwnerColumns());

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

    protected static function applyPaidPaymentMatch(QueryBuilder $query): QueryBuilder
    {
        if (DbSchema::hasColumn('payments', 'custom_form_entry_id')) {
            return $query->whereColumn('payments.custom_form_entry_id', 'custom_form_entries.id');
        }

        return $query
            ->whereColumn('payments.form_id', 'custom_form_entries.custom_form_id')
            ->where(fn (QueryBuilder $matchQuery): QueryBuilder => static::applyPaymentOwnerMatch($matchQuery));
    }

    protected static function existingOwnerColumns(): array
    {
        return collect(['created_by', 'user_id', 'created_by_id'])
            ->filter(fn (string $column): bool => DbSchema::hasColumn('custom_form_entries', $column))
            ->values()
            ->all();
    }

    protected static function currentUserCanViewAllRecords(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return method_exists($user, 'hasEffectiveStaffRole')
            ? $user->hasEffectiveStaffRole()
            : $user->registration_type === 'admin';
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentStatusLists::route('/'),
            'create' => CreatePaymentStatusList::route('/create'),
        ];
    }
}
