<?php

namespace App\Filament\Admin\Resources\SmsLogs\Tables;

use App\Models\SmsLog;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SmsLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('sms_logs.fields.created_at'))
                    ->dateTime('d-m-Y H:i:s')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label(__('sms_logs.fields.user'))
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('phone')
                    ->label(__('sms_logs.fields.phone'))
                    ->description(fn (SmsLog $record): ?string => $record->sent_to
                        ? __('sms_logs.fields.sent_to', ['phone' => $record->sent_to])
                        : null)
                    ->searchable(),

                TextColumn::make('content')
                    ->label(__('sms_logs.fields.content'))
                    ->limit(70)
                    ->tooltip(fn (SmsLog $record): string => $record->content)
                    ->searchable(),

                TextColumn::make('source')
                    ->label(__('sms_logs.fields.source'))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __('sms_logs.sources.'.$state) : '—')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label(__('sms_logs.fields.status'))
                    ->formatStateUsing(fn (string $state): string => __('sms_logs.statuses.'.$state))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'sent' ? 'success' : 'danger'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('sms_logs.fields.status'))
                    ->options([
                        'sent' => __('sms_logs.statuses.sent'),
                        'failed' => __('sms_logs.statuses.failed'),
                    ]),
                SelectFilter::make('source')
                    ->label(__('sms_logs.fields.source'))
                    ->options(collect(SmsLog::SOURCES)->mapWithKeys(fn (string $source): array => [$source => __('sms_logs.sources.'.$source)])->all()),
            ])
            ->recordActions([
                Action::make('details')
                    ->label(__('sms_logs.actions.details'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(__('sms_logs.model_label'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('sms_logs.actions.close'))
                    ->modalContent(fn (SmsLog $record) => view('filament.admin.sms-logs.details', ['log' => $record])),
            ]);
    }
}
