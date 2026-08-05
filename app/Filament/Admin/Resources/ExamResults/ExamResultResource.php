<?php

namespace App\Filament\Admin\Resources\ExamResults;

use App\Filament\Admin\Resources\ExamResults\Pages;
use App\Filament\Admin\Resources\ExamResults\Tables\ExamResultsTable;
use App\Filament\Concerns\AdminOnly;
use App\Models\ExamResult;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

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
            ->where('data->candidate_status', 'passed')
            ->where(function (Builder $query): void {
                $query->whereNull('data->' . static::HIDDEN_FLAG)
                    ->orWhere('data->' . static::HIDDEN_FLAG, false)
                    ->orWhere('data->' . static::HIDDEN_FLAG, 'false')
                    ->orWhere('data->' . static::HIDDEN_FLAG, 0)
                    ->orWhere('data->' . static::HIDDEN_FLAG, '0');
            })
            ->latest('id');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return ExamResultsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExamResults::route('/'),
        ];
    }
}
