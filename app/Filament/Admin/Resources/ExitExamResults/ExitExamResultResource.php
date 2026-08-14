<?php

namespace App\Filament\Admin\Resources\ExitExamResults;

use App\Filament\Admin\Resources\ExamResults\Tables\ExamResultsTable;
use App\Filament\Admin\Resources\ExitExamResults\Pages\ListExitExamResults;
use App\Filament\Concerns\AdminOnly;
use App\Models\ExitExamResult;
use App\Support\PassedResultMenuOptions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExitExamResultResource extends Resource
{
    use AdminOnly;

    public const HIDDEN_FLAG = 'hidden_from_exit_exam_results';

    protected static ?string $model = ExitExamResult::class;

    protected static ?string $slug = 'exit-exam-results';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('navigation.exit_exam_results');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.candidates');
    }

    public static function getModelLabel(): string
    {
        return __('exit_exam_results.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('exit_exam_results.plural_model_label');
    }

    public static function getResultMenuTarget(): string
    {
        return PassedResultMenuOptions::EXIT_EXAM_RESULTS;
    }

    public static function getResultModuleLabel(): string
    {
        return __('navigation.exit_exam_results');
    }

    public static function getEloquentQuery(): Builder
    {
        return ExamResultsTable::applyPassedResultMenuFilter(
            query: parent::getEloquentQuery()->with(['creator', 'customForm']),
            resultMenu: static::getResultMenuTarget(),
            hiddenFlag: static::HIDDEN_FLAG,
        )->latest('id');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return ExamResultsTable::configure(
            table: $table,
            resultMenu: static::getResultMenuTarget(),
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExitExamResults::route('/'),
        ];
    }
}
