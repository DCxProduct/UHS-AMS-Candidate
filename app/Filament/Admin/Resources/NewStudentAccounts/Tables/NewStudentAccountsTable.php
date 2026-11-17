<?php

namespace App\Filament\Admin\Resources\NewStudentAccounts\Tables;

use App\Filament\Admin\Resources\NewStudentAccounts\NewStudentAccountResource;
use App\Models\NewStudentAccount;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use App\Support\NotificationLanguage;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
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
            ->recordUrl(fn (NewStudentAccount $record): ?string => NewStudentAccountResource::canEdit($record)
                ? NewStudentAccountResource::getUrl('edit', ['record' => $record])
                : null)
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
                    EditAction::make()->label(__('new_student_accounts.actions.edit')),
                    Action::make('activate_account')
                        ->label(__('new_student_accounts.actions.activate'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->authorize(fn (NewStudentAccount $record): bool => NewStudentAccountResource::can('activateAccount', $record))
                        ->visible(fn (NewStudentAccount $record): bool => ! $record->is_active)
                        ->action(function (NewStudentAccount $record): void {
                            abort_unless(NewStudentAccountResource::can('activateAccount', $record), 403);
                            $record->update(['is_active' => true]);

                            Notification::make()
                                ->title(NotificationLanguage::trans('new_student_accounts.notifications.activated'))
                                ->success()
                                ->send();
                        }),
                    Action::make('deactivate_account')
                        ->label(__('new_student_accounts.actions.deactivate'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->authorize(fn (NewStudentAccount $record): bool => NewStudentAccountResource::can('deactivateAccount', $record))
                        ->visible(fn (NewStudentAccount $record): bool => $record->is_active)
                        ->action(function (NewStudentAccount $record): void {
                            abort_unless(NewStudentAccountResource::can('deactivateAccount', $record), 403);
                            $record->update(['is_active' => false]);

                            Notification::make()
                                ->title(NotificationLanguage::trans('new_student_accounts.notifications.deactivated'))
                                ->success()
                                ->send();
                        }),
                    DeleteAction::make()->label(__('new_student_accounts.actions.delete')),
                ])
                    ->label('')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip(__('new_student_accounts.actions.actions')),
            ]);
    }
}
