<?php

namespace App\Filament\Admin\Resources\NewStudentAccounts\Tables;

use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NewStudentAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->searchPlaceholder(__('new_student_accounts.search'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('new_student_accounts.fields.row_number'))
                    ->rowIndex()
                    ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                    ->alignCenter()
                    ->width('60px'),
                TextColumn::make('student_id')
                    ->label(__('new_student_accounts.fields.student_id'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('new_student_accounts.fields.email'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('new_student_accounts.fields.phone'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name_latin')
                    ->label(__('new_student_accounts.fields.first_name_latin'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name_latin')
                    ->label(__('new_student_accounts.fields.last_name_latin'))
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('new_student_accounts.fields.is_active'))
                    ->boolean()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('new_student_accounts.fields.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('new_student_accounts.fields.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label(__('new_student_accounts.actions.view')),
                    EditAction::make()->label(__('new_student_accounts.actions.edit')),
                    DeleteAction::make()->label(__('new_student_accounts.actions.delete')),
                ])
                    ->label('')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip(__('new_student_accounts.actions.actions')),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
            ]);
    }
}
