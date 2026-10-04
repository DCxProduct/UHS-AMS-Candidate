<?php

namespace App\Filament\Admin\Resources\ExamResults;

use App\Filament\Admin\Resources\ExamResults\Tables\ExamResultsTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\ExamResult;
use App\Support\PassedResultMenuOptions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExamResultResource extends Resource
{
    use AdminOnly;

    public const HIDDEN_FLAG = 'hidden_from_exam_results';

    protected static ?string $model = ExamResult::class;

    protected static ?string $slug = 'exam-results';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('navigation.exam_results');
    }

    public static function getNavigationBadge(): ?string
    {
        return number_format(static::getEloquentQuery()->count());
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.candidates');
    }

    public static function getModelLabel(): string
    {
        return __('exam_results.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('exam_results.plural_model_label');
    }

    public static function getResultMenuTarget(): string
    {
        return PassedResultMenuOptions::EXAM_RESULTS;
    }

    public static function getResultModuleLabel(): string
    {
        return __('navigation.exam_results');
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
            'index' => Pages\ListExamResults::route('/'),
        ];
    }
}
