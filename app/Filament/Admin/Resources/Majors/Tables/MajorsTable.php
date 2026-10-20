<?php

namespace App\Filament\Admin\Resources\Majors\Tables;

use App\Models\Major;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                IconColumn::make('is_active')
                    ->label(__('majors.fields.is_active'))
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label(__('majors.fields.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),
                TextColumn::make('updated_at')
                    ->label(__('majors.fields.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),
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
