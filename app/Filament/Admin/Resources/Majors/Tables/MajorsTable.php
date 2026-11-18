<?php

namespace App\Filament\Admin\Resources\Majors\Tables;

use App\Models\Major;
use App\Support\CaseInsensitiveSearch;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MajorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->searchPlaceholder(__('majors.search'))
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('majors.table.no'))
                    ->rowIndex()
                    ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                    ->alignCenter()
                    ->width('60px'),
                TextColumn::make('abbreviation')
                    ->label(__('majors.fields.abbreviation'))
                    ->searchable(),
                TextColumn::make('localized_name')
                    ->label(__('majors.table.name'))
                    ->getStateUsing(fn (Major $record): string => $record->localized_name)
                    ->searchable(['name_en', 'name_kh']),
                TextColumn::make('degreeLevels.localized_label')
                    ->label(__('majors.fields.degree_levels'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('degreeLevels', fn (Builder $degrees): Builder => CaseInsensitiveSearch::applyAny($degrees, [
                        $degrees->getModel()->qualifyColumn('label_en'),
                        $degrees->getModel()->qualifyColumn('label_kh'),
                    ], $search)))
                    ->listWithLineBreaks()
                    ->bulleted(),
                IconColumn::make('is_active')
                    ->label(__('majors.fields.is_active'))
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label(__('majors.fields.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyDate($query, $query->getModel()->qualifyColumn('created_at'), $search)),
                TextColumn::make('updated_at')
                    ->label(__('majors.fields.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyDate($query, $query->getModel()->qualifyColumn('updated_at'), $search)),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->label('')
                    ->icon('heroicon-m-ellipsis-vertical'),
            ]);
    }
}
