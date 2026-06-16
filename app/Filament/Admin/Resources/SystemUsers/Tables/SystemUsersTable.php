<?php

namespace App\Filament\Admin\Resources\SystemUsers\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SystemUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->searchPlaceholder(__('system_users.search'))
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('system_users.fields.no'))
                    ->rowIndex()
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('email')
                    ->label(__('system_users.fields.email'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label(__('system_users.fields.phone'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('username')
                    ->label(__('system_users.fields.username'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email_verified_at')
                    ->label(__('system_users.fields.email_verified_at'))
                    ->dateTime('M d, Y H:i:s')
                    ->placeholder('-')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('system_users.fields.is_active'))
                    ->boolean()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('system_users.fields.created_at'))
                    ->dateTime('M d, Y H:i:s')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('system_users.fields.updated_at'))
                    ->dateTime('M d, Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('system_users.fields.is_active'))
                    ->trueLabel(__('system_users.filters.active'))
                    ->falseLabel(__('system_users.filters.inactive'))
                    ->native(false),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label(__('system_users.actions.edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning'),

                    Action::make('activate_account')
                        ->label(__('system_users.actions.activate'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn ($record): bool => ! (bool) $record->is_active)
                        ->action(function ($record): void {
                            $record->activateAccount();

                            Notification::make()
                                ->title(__('system_users.notifications.activated'))
                                ->success()
                                ->send();
                        }),

                    Action::make('deactivate_account')
                        ->label(__('system_users.actions.deactivate'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn ($record): bool => (bool) $record->is_active)
                        ->action(function ($record): void {
                            $record->deactivateAccount();

                            Notification::make()
                                ->title(__('system_users.notifications.deactivated'))
                                ->success()
                                ->send();
                        }),

                    DeleteAction::make()
                        ->label(__('system_users.actions.delete'))
                        ->icon('heroicon-o-trash')
                        ->color('danger'),
                ])
                    ->label('')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('warning')
                    ->tooltip(__('system_users.actions.actions')),
            ]);
    }
}
