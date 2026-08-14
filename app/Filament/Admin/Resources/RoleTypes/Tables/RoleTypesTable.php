<?php

namespace App\Filament\Admin\Resources\RoleTypes\Tables;

use App\Models\RoleType;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoleTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->searchPlaceholder(__('role_types.search'))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('role_types.table.no'))
                    ->rowIndex()
                    ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('key')
                    ->label(__('role_types.table.key'))
                    ->hidden()
                    ->badge(),

                TextColumn::make('localized_label')
                    ->label(__('role_types.table.label'))
                    ->getStateUsing(fn (RoleType $record): string => $record->localized_label)
                    ->searchable(['label_en', 'label_kh', 'key']),

                IconColumn::make('is_active')
                    ->label(__('role_types.table.is_active'))
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label(__('role_types.table.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),

                TextColumn::make('updated_at')
                    ->label(__('role_types.table.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label(__('role_types.actions.edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning'),
                    DeleteAction::make()
                        ->label(__('role_types.actions.delete'))
                        ->icon('heroicon-o-trash')
                        ->color('danger'),
                ])
                    ->label('')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('warning')
                    ->tooltip(__('role_types.actions.actions')),
            ]);
    }
}
