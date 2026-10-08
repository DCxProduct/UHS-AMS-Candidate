<?php

namespace App\Filament\Admin\Resources\CandidateEntranceStatistics;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Tables\CandidateEntranceStatisticsTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\CandidateEntranceStatistic;
use App\Models\WorkflowNotification;
use App\Support\StatisticsMenuOptions;
use BackedEnum;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Schema as DbSchema;

class CandidateEntranceStatisticResource extends Resource
{
    use AdminOnly;

    public const HIDDEN_FLAG = 'hidden_from_review_applications';

    protected static ?string $model = CandidateEntranceStatistic::class;

    protected static ?string $slug = 'entrance-exam-statistics';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('navigation.candidate_entrance_statistics');
    }

    public static function getNavigationBadge(): ?string
    {
        return number_format(
            static::getEloquentQuery()
                ->where(function (Builder $query): void {
                    $query
                        ->whereNull('data->candidate_status')
                        ->orWhere('data->candidate_status', '')
                        ->orWhere('data->candidate_status', 'pending');
                })
                ->count(),
        );
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
        return __('candidate_entrance_statistics.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('candidate_entrance_statistics.plural_model_label');
    }

    public static function getEloquentQuery(): Builder
    {
        $configuredPaymentFormIds = WorkflowNotification::formIdsWithStage(WorkflowStageType::Payment);
        $assignedWorkflowFormIds = WorkflowNotification::assignedFormIds();
        $paymentFormsQuery = CustomForm::query()->where('requires_payment', true);

        if ($assignedWorkflowFormIds !== []) {
            $paymentFormsQuery->where(function (Builder $query) use ($configuredPaymentFormIds, $assignedWorkflowFormIds): void {
                $query->whereIn('id', $configuredPaymentFormIds)
                    ->orWhereNotIn('id', $assignedWorkflowFormIds);
            });
        }

        $paymentFormIds = $paymentFormsQuery->pluck('id')->all();

        return parent::getEloquentQuery()
            ->with([
                'creator',
                'customForm',
            ])
            ->whereHas('customForm', function (Builder $query): void {
                $query->where('is_active', true)
                    ->where('slug', '!=', 'profile')
                    ->where('statistics_menu', StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS);
            })
            ->whereIn('review_status', [
                'accepted',
                'approved',
                'passed',
            ])
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('data->candidate_status')
                    ->orWhere('data->candidate_status', '')
                    ->orWhere('data->candidate_status', 'pending')
                    ->orWhere('data->candidate_status', 'passed');
            })
            ->where(function (Builder $query) use ($paymentFormIds): void {
                $query
                    ->whereNotIn('custom_form_id', $paymentFormIds)
                    ->orWhere(function (Builder $query) use ($paymentFormIds): void {
                        $query
                            ->whereIn('custom_form_id', $paymentFormIds)
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
        return CandidateEntranceStatisticsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCandidateEntranceStatistics::route('/'),
        ];
    }
}
