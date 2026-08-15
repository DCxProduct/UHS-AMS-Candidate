<?php

namespace App\Filament\Admin\Resources\CandidateTypes\Tables;

use App\Models\UserType;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use App\Support\UserTypeOptions;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CandidateTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->searchPlaceholder(__('candidate_types.search'))
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('candidate_types.table.no'))
                    ->rowIndex()
                    ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('preview')
                    ->label(__('candidate_types.table.preview'))
                    ->getStateUsing(fn (UserType $record): string => UserTypeOptions::formatPreviewLabel($record->key))
                    ->searchable(['label_en', 'label_kh', 'key']),

                TextColumn::make('group_name')
                    ->label(__('candidate_types.table.group_name'))
                    ->formatStateUsing(fn (?string $state): string => UserTypeOptions::formatGroupLabel($state))
                    ->badge()
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('candidate_types.table.is_active'))
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label(__('candidate_types.table.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),

                TextColumn::make('updated_at')
                    ->label(__('candidate_types.table.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label(__('candidate_types.actions.edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning'),

                    DeleteAction::make()
                        ->label(__('candidate_types.actions.delete'))
                        ->icon('heroicon-o-trash')
                        ->color('danger'),
                ])
                    ->label('')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('warning')
                    ->tooltip(__('candidate_types.actions.actions')),
            ]);
    }
}
