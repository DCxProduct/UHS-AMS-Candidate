<?php

namespace App\Filament\Admin\Resources\SystemUsers\Tables;

use App\Models\SystemUser;
use App\Models\User;
use App\Support\UserTypeOptions;
use App\Support\NotificationLanguage;
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

                TextColumn::make('candidate_type')
                    ->label(__('system_users.fields.candidate_type'))
                    ->getStateUsing(function (SystemUser $record): string {
                        $candidateRole = static::resolveCandidateRole($record);

                        if (! $candidateRole) {
                            return '-';
                        }

                        if ($candidateRole === UserTypeOptions::BASE_ROLE) {
                            return UserTypeOptions::formatLabel($candidateRole);
                        }

                        $candidateType = UserTypeOptions::findByKey((string) $candidateRole);

                        if (! $candidateType) {
                            return '-';
                        }

                        return $candidateType->getLocalizedLabel();
                    })
                    ->badge()
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('roles', $direction))
                    ->color(function (SystemUser $record): string {
                        $candidateRole = static::resolveCandidateRole($record);

                        if (! $candidateRole) {
                            return 'gray';
                        }

                        if ($candidateRole === UserTypeOptions::BASE_ROLE) {
                            return UserTypeOptions::normalizeColor('blue');
                        }

                        $candidateType = UserTypeOptions::findByKey((string) $candidateRole);

                        return $candidateType
                            ? UserTypeOptions::normalizeColor($candidateType->color)
                            : 'gray';
                    }),

                IconColumn::make('is_active')
                    ->label(__('system_users.fields.is_active'))
                    ->boolean()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('system_users.fields.created_at'))
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('system_users.fields.updated_at'))
                    ->date('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('system_users.fields.is_active'))
                    ->trueLabel(__('system_users.filters.active'))
                    ->falseLabel(__('system_users.filters.inactive'))
                    ->native(false),
            ])
            ->deferFilters(false)
            ->filtersApplyAction(fn (Action $action): Action => $action->hidden())
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
                                ->title(NotificationLanguage::trans('system_users.notifications.activated'))
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
                                ->title(NotificationLanguage::trans('system_users.notifications.deactivated'))
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

    protected static function resolveCandidateRole(SystemUser $record): ?string
    {
        $roles = $record->roles;

        if (is_string($roles)) {
            $decoded = json_decode($roles, true);
            $roles = is_array($decoded) ? $decoded : [$roles];
        }

        if (is_array($roles)) {
            $roleCollection = collect($roles)
                ->filter(fn ($role): bool => filled($role))
                ->map(fn ($role): string => trim((string) $role))
                ->values();

            $candidateRole = $roleCollection
                ->first(fn (string $role): bool => strcasecmp($role, 'Student') !== 0);

            if ($candidateRole) {
                return $candidateRole;
            }

            if ($roleCollection->contains(fn (string $role): bool => strcasecmp($role, 'Student') === 0)) {
                return UserTypeOptions::BASE_ROLE;
            }
        }

        $loginUser = static::findLinkedLoginUser($record);

        if ($loginUser?->registration_type === 'student') {
            return UserTypeOptions::BASE_ROLE;
        }

        return null;
    }

    protected static function findLinkedLoginUser(SystemUser $record): ?User
    {
        return User::query()
            ->when(filled($record->username), fn ($query) => $query->orWhere('username', $record->username))
            ->when(filled($record->email), fn ($query) => $query->orWhere('email', $record->email))
            ->when(filled($record->phone), fn ($query) => $query->orWhere('phone', $record->phone))
            ->first();
    }
}
