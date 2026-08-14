<?php

namespace App\Filament\Admin\Resources\DegreeLevels\Tables;

use App\Models\DegreeLevel;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DegreeLevelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->searchPlaceholder(__('degree_levels.search'))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('degree_levels.table.no'))
                    ->rowIndex()
                    ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('key')
                    ->label(__('degree_levels.table.key'))
                    ->badge()
                    ->hidden()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('localized_label')
                    ->label(__('degree_levels.table.label'))
                    ->getStateUsing(fn (DegreeLevel $record): string => $record->localized_label)
                    ->searchable(['label_en', 'label_kh', 'key']),

                IconColumn::make('is_active')
                    ->label(__('degree_levels.table.is_active'))
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label(__('degree_levels.table.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),

                TextColumn::make('updated_at')
                    ->label(__('degree_levels.table.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label(__('degree_levels.actions.edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning'),
                    DeleteAction::make()
                        ->label(__('degree_levels.actions.delete'))
                        ->icon('heroicon-o-trash')
                        ->color('danger'),
                ])
                    ->label('')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('warning')
                    ->tooltip(__('degree_levels.actions.actions')),
            ]);
    }
}
