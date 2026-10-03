<?php

namespace App\Filament\Admin\Resources\CandidateExitStatistics;

use App\Filament\Admin\Resources\CandidateExitStatistics\Schemas\CandidateExitStatisticForm;
use App\Filament\Admin\Resources\CandidateExitStatistics\Tables\CandidateExitStatisticsTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\CandidateExitStatistic;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CandidateExitStatisticResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = CandidateExitStatistic::class;

    protected static ?string $slug = 'exit-exam-statistics';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('navigation.candidate_exit_statistics');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.candidates');
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getModelLabel(): string
    {
        return __('candidate_exit_statistics.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('candidate_exit_statistics.plural_model_label');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('hidden_from_statistics')
                    ->orWhere('hidden_from_statistics', false)
                    ->orWhere('hidden_from_statistics', 0)
                    ->orWhere('hidden_from_statistics', '0');
            });
    }

    public static function form(Schema $schema): Schema
    {
        return CandidateExitStatisticForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CandidateExitStatisticsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCandidateExitStatistics::route('/'),
            'create' => Pages\CreateCandidateExitStatistic::route('/create'),
        ];
    }
}
