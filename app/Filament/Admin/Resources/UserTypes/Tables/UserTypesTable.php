<?php

namespace App\Filament\Admin\Resources\UserTypes\Tables;

use App\Models\UserType;
use App\Support\LocalizedDate;
use App\Support\UserTypeOptions;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->searchPlaceholder(__('user_types.search'))
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('user_types.table.no'))
                    ->rowIndex()
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('preview')
                    ->label(__('user_types.table.preview'))
                    ->getStateUsing(fn (UserType $record): string => UserTypeOptions::formatPreviewLabel($record->key))
                    ->searchable(['label_en', 'label_kh', 'key']),

                IconColumn::make('is_active')
                    ->label(__('user_types.table.is_active'))
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label(__('user_types.table.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),

                TextColumn::make('updated_at')
                    ->label(__('user_types.table.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label(__('user_types.actions.edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning'),

                    DeleteAction::make()
                        ->label(__('user_types.actions.delete'))
                        ->icon('heroicon-o-trash')
                        ->color('danger'),
                ])
                    ->label('')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('warning')
                    ->tooltip(__('user_types.actions.actions')),
            ]);
    }
}
