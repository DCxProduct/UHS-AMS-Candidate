<?php

namespace App\Filament\Admin\Resources\PaymentStatusLists;

use App\Enums\WorkflowPaymentSource;
use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\Payments\Schemas\PaymentForm;
use App\Filament\Admin\Resources\PaymentStatusLists\Pages\CreatePaymentStatusList;
use App\Filament\Admin\Resources\PaymentStatusLists\Pages\ListPaymentStatusLists;
use App\Filament\Admin\Resources\PaymentStatusLists\Tables\PaymentStatusListsTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\AmsPaymentRequest;
use App\Models\PaymentStatusList;
use App\Models\WorkflowNotification;
use App\Support\MenuRecordVisibility;
use App\Support\WorkflowProgressQuery;
use BackedEnum;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DbSchema;
use UnitEnum;

class PaymentStatusListResource extends Resource
{
    use AdminOnly;

    public const HIDDEN_FLAG = 'hidden_from_payment_status_lists';

    protected static ?string $model = PaymentStatusList::class;

    protected static ?string $slug = 'payment-status-lists';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('payment_status_lists.navigation_label');
    }

    public static function getNavigationBadge(): ?string
    {
        return number_format(static::getEloquentQuery()->count());
    }

    public static function getNavigationGroup(): string|UnitEnum|null
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
        return static::getPaymentApplicationsQuery(includeCleared: false);
    }

    public static function getDashboardEloquentQuery(): Builder
    {
        return static::getPaymentApplicationsQuery(includeCleared: true);
    }

    protected static function getPaymentApplicationsQuery(bool $includeCleared): Builder
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
            ->where(function (Builder $query) use ($paymentFormIds): void {
                if (! DbSchema::hasColumn('custom_form_entries', 'workflow_snapshot')) {
                    $query->whereIn('custom_form_id', $paymentFormIds);

                    return;
                }

                $query->where(function (Builder $ordered): void {
                    WorkflowProgressQuery::ordered($ordered)
                        ->where(function (Builder $progress): void {
                            $progress->where('data->workflow_progress->current_type', WorkflowStageType::Payment->value)
                                ->orWhere('data->workflow_progress->payment_completed', true);
                        });
                })->orWhere(function (Builder $legacy) use ($paymentFormIds): void {
                    WorkflowProgressQuery::legacy($legacy)
                        ->where(function (Builder $query) use ($paymentFormIds): void {
                            $query->where(function (Builder $query): void {
                                $query->where('workflow_snapshot->has_workflow', true)
                                    ->where('workflow_snapshot->requires_payment', true);
                            })->orWhere(function (Builder $query): void {
                                $query->where('workflow_snapshot->has_workflow', false)
                                    ->whereHas('customForm', fn (Builder $formQuery): Builder => $formQuery->where('requires_payment', true));
                            })->orWhere(function (Builder $query) use ($paymentFormIds): void {
                                $query->whereNull('workflow_snapshot')
                                    ->whereIn('custom_form_id', $paymentFormIds);
                            });
                        });
                });
            })
            ->where(function (Builder $query): void {
                $query->where(fn (Builder $ordered): Builder => WorkflowProgressQuery::ordered($ordered))
                    ->orWhere(function (Builder $legacy): void {
                        WorkflowProgressQuery::legacy($legacy)
                            ->where(function (Builder $reviewed): void {
                                $reviewed->where('data->candidate_status', 'passed')
                                    ->orWhereIn('review_status', ['approved', 'accepted', 'passed'])
                                    ->orWhereIn('data->registration_status', ['approved', 'accepted', 'passed']);
                            });
                    });
            })
            ->when(! $includeCleared, function (Builder $query): void {
                MenuRecordVisibility::visible($query, static::HIDDEN_FLAG);
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

        return static::excludeAmsPaymentApplications($query);
    }

    protected static function excludeAmsPaymentApplications(Builder $query): Builder
    {
        $amsWorkflowIds = WorkflowNotification::query()->get(['id', 'stages'])
            ->filter(fn (WorkflowNotification $workflow): bool => $workflow->paymentSource() === WorkflowPaymentSource::Ams)
            ->modelKeys();
        $amsFormIds = DB::table('workflow_notification_forms')
            ->whereIn('workflow_notification_id', $amsWorkflowIds)
            ->pluck('custom_form_id')->all();

        // Cashier lists contain only local collections, whether paid or unpaid.
        $query->whereNotExists(AmsPaymentRequest::paidForApplicationQuery()->toBase());

        if (! DbSchema::hasColumn('custom_form_entries', 'workflow_snapshot')) {
            return $query->whereNotIn('custom_form_id', $amsFormIds);
        }

        return $query->where(function (Builder $local) use ($amsFormIds): void {
            // Only entries without a snapshot use the current form configuration.
            $local->where(function (Builder $legacy) use ($amsFormIds): void {
                $legacy->whereNull('workflow_snapshot')
                    ->whereNotIn('custom_form_id', $amsFormIds);
            })->orWhere(function (Builder $frozen): void {
                $frozen->whereNotNull('workflow_snapshot')
                    ->where(function (Builder $source): void {
                        $source->where('workflow_snapshot->has_workflow', false)
                            ->orWhereNull('workflow_snapshot->has_workflow')
                            ->orWhereNull('workflow_snapshot->stages')
                            ->orWhere(function (Builder $stages): void {
                                if ($stages->getConnection()->getDriverName() === 'sqlite') {
                                    $stages->whereNotExists(function (QueryBuilder $stage): void {
                                        $stage->selectRaw('1')
                                            ->fromRaw("json_each(custom_form_entries.workflow_snapshot, '$.stages') as payment_stage")
                                            ->where(function (QueryBuilder $wrapped): void {
                                                $wrapped->where('payment_stage.value->data->stage_type', WorkflowStageType::Payment->value)
                                                    ->where('payment_stage.value->data->payment_source', WorkflowPaymentSource::Ams->value);
                                            })->orWhere(function (QueryBuilder $flat): void {
                                                $flat->where('payment_stage.value->stage_type', WorkflowStageType::Payment->value)
                                                    ->where('payment_stage.value->payment_source', WorkflowPaymentSource::Ams->value);
                                            });
                                    });

                                    return;
                                }

                                $amsStage = ['stage_type' => WorkflowStageType::Payment->value, 'payment_source' => WorkflowPaymentSource::Ams->value];
                                $stages->whereJsonDoesntContain('workflow_snapshot->stages', [['data' => $amsStage]])
                                    ->whereJsonDoesntContain('workflow_snapshot->stages', [$amsStage]);
                            });
                    });
            });
        });
    }

    public static function getUnpaidEloquentQuery(bool $includeCleared = false): Builder
    {
        $query = $includeCleared
            ? static::getDashboardEloquentQuery()
            : static::getEloquentQuery();

        return $query
            ->whereNotExists(AmsPaymentRequest::paidForApplicationQuery()->toBase())
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
