<?php

namespace App\Filament\Admin\Resources\NotificationTemplates\Tables;

use App\Filament\Admin\Resources\NotificationTemplates\NotificationTemplateResource;
use App\Models\NotificationTemplate;
use App\Services\NotificationService;
use App\Support\LocalizedDate;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class NotificationTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'asc')
            ->recordUrl(fn (NotificationTemplate $record): string => NotificationTemplateResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('code')
                    ->label(__('notification_templates.table.code'))
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('notification_templates.table.name'))
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('notification_templates.table.type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'request' => __('notification_templates.types.request'),
                        default => __('notification_templates.types.announcement'),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'request' => 'warning',
                        default => 'info',
                    }),

                TextColumn::make('channels')
                    ->label(__('notification_templates.table.channels'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : (string) $state),

                IconColumn::make('is_active')
                    ->label(__('notification_templates.table.is_active'))
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('updated_at')
                    ->label(__('notification_templates.table.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('notification_templates.table.type'))
                    ->options([
                        'announcement' => __('notification_templates.types.announcement'),
                        'request' => __('notification_templates.types.request'),
                    ]),

                TernaryFilter::make('is_active')
                    ->label(__('notification_templates.table.is_active')),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('test_send')
                        ->label(__('notification_templates.actions.test_send'))
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->modalHeading(fn (NotificationTemplate $record): string => __('notification_templates.modals.test_send_heading', ['name' => $record->name]))
                        ->modalDescription(__('notification_templates.modals.test_send_description'))
                        ->modalSubmitActionLabel(__('notification_templates.actions.submit_test'))
                        ->schema([
                            TextInput::make('email')
                                ->label(__('Email'))
                                ->email()
                                ->default(fn () => auth()->user()?->email)
                                ->required(),

                            TextInput::make('stage_name')
                                ->label(__('notification_templates.variables.stage_name'))
                                ->default('Entrance Examination 2026')
                                ->required(),

                            Textarea::make('notes')
                                ->label(__('notification_templates.variables.notes'))
                                ->default('All submitted documents have been verified successfully.')
                                ->rows(3),

                            CheckboxList::make('channels')
                                ->label(__('notification_templates.fields.channels'))
                                ->options([
                                    'database' => __('notification_templates.channels.database'),
                                    'mail' => __('notification_templates.channels.mail'),
                                ])
                                ->default(fn (NotificationTemplate $record): array => $record->channels ?? ['database', 'mail'])
                                ->columns(2)
                                ->required(),
                        ])
                        ->action(function (NotificationTemplate $record, array $data): void {
                            $actor = auth()->user();

                            if (! $actor) {
                                return;
                            }

                            $service = app(NotificationService::class);
                            $service->sendToUser($actor, [
                                'template_id' => $record->id,
                                'recipient_email' => $data['email'],
                                'stage_name' => $data['stage_name'],
                                'notes' => $data['notes'],
                                'channels' => $data['channels'],
                                'action_url' => url('/notification-templates'),
                            ]);

                            Notification::make()
                                ->title(__('notification_templates.notifications.test_sent_title'))
                                ->body(__('notification_templates.notifications.test_sent_body', [
                                    'email' => $data['email'],
                                    'channels' => implode(', ', (array) $data['channels']),
                                ]))
                                ->success()
                                ->send();
                        }),
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
