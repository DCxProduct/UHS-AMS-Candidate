<?php

namespace App\Filament\Admin\Resources\UserTypes\Tables;

use App\Models\UserType;
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
            ->defaultSort('display_order')
            ->reorderable('display_order')
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('user_types.table.no'))
                    ->rowIndex()
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('key')
                    ->label(__('user_types.table.key'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('preview')
                    ->label(__('user_types.table.preview'))
                    ->getStateUsing(fn (UserType $record): string => UserTypeOptions::formatPreviewLabel($record->key))
                    ->searchable(['label_en', 'label_kh', 'key'])
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('label_en', $direction)),

                TextColumn::make('color')
                    ->label(__('user_types.table.color'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => UserTypeOptions::colorOptions()[UserTypeOptions::canonicalColor($state)] ?? ($state ?? '-'))
                    ->color(fn (UserType $record): string => UserTypeOptions::normalizeColor($record->color))
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('user_types.table.is_active'))
                    ->boolean()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('user_types.table.created_at'))
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('user_types.table.updated_at'))
                    ->date('M d, Y')
                    ->sortable(),
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
