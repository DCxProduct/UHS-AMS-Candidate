<?php

namespace App\Filament\Admin\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use App\Models\SystemUser;
use App\Models\User;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use App\Support\UserTypeOptions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('audit_logs.fields.no'))
                    ->rowIndex()
                    ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('module')
                    ->hidden()
                    ->label(__('audit_logs.fields.module'))
                    ->formatStateUsing(fn (?string $state): string => self::translateModule($state)),

                TextColumn::make('action')
                    ->label(__('audit_logs.fields.action'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::translateAction($state))
                    ->color(fn (?string $state): string => match ($state) {
                        'created', 'login' => 'success',
                        'updated', 'logout', 'reverted' => 'warning',
                        'deleted' => 'danger',
                        default => 'info',
                    }),

                TextColumn::make('description')
                    ->label(__('audit_logs.fields.description'))
                    ->formatStateUsing(fn (?string $state, AuditLog $record): string => self::translateDescription($record, $state))
                    ->limit(70)
                    ->tooltip(fn (?string $state, AuditLog $record): ?string => filled($state) ? self::translateDescription($record, $state) : null)
                    ->wrap(),

                TextColumn::make('role')
                    ->label(__('audit_logs.fields.role'))
                    ->badge()
                    ->getStateUsing(fn (AuditLog $record): string => self::actorRoleLabel($record))
                    ->placeholder('-')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('created_at')
                    ->label(__('audit_logs.fields.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::fullDateTime($state))
                    ->color('primary'),

                TextColumn::make('ip_address')
                    ->label(__('audit_logs.fields.ip_address'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                Filter::make('audit_log_filters')
                    ->label(new HtmlString('&nbsp;'))
                    ->schema([
                        Select::make('action')
                            ->label(__('audit_logs.fields.action'))
                            ->options(fn (): array => collect(self::scopedQuery()
                                ->select('action')
                                ->distinct()
                                ->orderBy('action')
                                ->pluck('action', 'action')
                                ->toArray())
                                ->mapWithKeys(fn (string $label, string $value): array => [$value => self::translateAction($label)])
                                ->toArray())
                            ->native(false)
                            ->searchable()
                            ->live(),

                        Select::make('module')
                            ->label(__('audit_logs.fields.module'))
                            ->options(fn (): array => collect(self::scopedQuery()
                                ->select('module')
                                ->distinct()
                                ->orderBy('module')
                                ->pluck('module', 'module')
                                ->toArray())
                                ->mapWithKeys(fn (string $label, string $value): array => [$value => self::translateModule($label)])
                                ->toArray())
                            ->native(false)
                            ->searchable()
                            ->live(),

                        DatePicker::make('from_date')
                            ->label(__('audit_logs.fields.created_at'))
                            ->placeholder(__('audit_logs.placeholders.date'))
                            ->displayFormat('d-m-Y')
                            ->maxDate(Carbon::today())
                            ->native(false),
                    ])
                    ->columns(4)
                    ->columnSpanFull()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['action'] ?? null),
                                fn (Builder $query): Builder => $query->where('action', $data['action'])
                            )
                            ->when(
                                filled($data['module'] ?? null),
                                fn (Builder $query): Builder => $query->where('module', $data['module'])
                            )
                            ->when(
                                filled($data['from_date'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('created_at', $data['from_date'])
                            );
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns(3)
            ->recordActions([])
            ->toolbarActions([]);
    }

    protected static function cleanDescription(?string $value): string
    {
        if (blank($value)) {
            return '-';
        }

        return preg_replace('/\s+#\d+\b/', '', $value) ?: (string) $value;
    }

    protected static function translateAction(?string $value): string
    {
        if (blank($value)) {
            return '-';
        }

        $key = str($value)->snake()->toString();
        $translated = __('audit_logs.values.actions.' . $key);

        return $translated === 'audit_logs.values.actions.' . $key ? (string) $value : $translated;
    }

    protected static function translateModule(?string $value): string
    {
        if (blank($value)) {
            return '-';
        }

        $key = str($value)->snake()->toString();
        $translated = __('audit_logs.values.modules.' . $key);

        return $translated === 'audit_logs.values.modules.' . $key ? (string) $value : $translated;
    }

    protected static function translateDescription(AuditLog $record, ?string $value): string
    {
        $cleaned = self::cleanDescription($value);

        if ($cleaned === '-') {
            return '-';
        }

        $action = (string) $record->action;
        $module = self::translateModule($record->module);

        return match ($action) {
            'login' => __('audit_logs.descriptions.login'),
            'logout' => __('audit_logs.descriptions.logout'),
            'created', 'updated', 'deleted' => __('audit_logs.descriptions.model_action', [
                'action' => self::translateAction($action),
                'module' => $module,
            ]),
            'approved', 'rejected', 'passed', 'submitted', 'saved_draft', 'reverted' => __('audit_logs.descriptions.action_module', [
                'action' => self::translateAction($action),
                'module' => $module,
            ]),
            default => $cleaned,
        };
    }

    protected static function scopedQuery(): Builder
    {
        return AuditLog::query();
    }

    protected static function actorRoleLabel(AuditLog $record): string
    {
        $actor = $record->actor;

        if (! $actor) {
            return __('audit_logs.values.system');
        }

        $systemUser = match (true) {
            $actor instanceof SystemUser => $actor,
            $actor instanceof User => static::findLinkedSystemUser($actor),
            default => null,
        };

        if (! $systemUser) {
            return '-';
        }

        $roles = collect($systemUser->roles ?? [])
            ->when(is_string($systemUser->roles), function ($collection) use ($systemUser) {
                $decoded = json_decode((string) $systemUser->roles, true);

                return collect(is_array($decoded) ? $decoded : [$systemUser->roles]);
            })
            ->filter(fn ($role): bool => filled($role))
            ->map(fn ($role): string => strtolower(trim((string) $role)))
            ->reject(fn (string $role): bool => UserTypeOptions::isCandidateManagedRole($role))
            ->map(fn ($role): string => self::formatRoleLabel((string) $role))
            ->unique()
            ->values();

        return $roles->isNotEmpty() ? $roles->join(', ') : '-';
    }

    protected static function findLinkedSystemUser(User $user): ?SystemUser
    {
        return SystemUser::query()
            ->when(filled($user->username), fn ($query) => $query->orWhere('username', $user->username))
            ->when(filled($user->email), fn ($query) => $query->orWhere('email', $user->email))
            ->when(filled($user->phone), fn ($query) => $query->orWhere('phone', $user->phone))
            ->first();
    }

    protected static function formatRoleLabel(string $role): string
    {
        $normalized = strtolower(trim($role));

        if ($normalized === '') {
            return '';
        }

        $formatted = UserTypeOptions::formatLabel($normalized);

        return $formatted !== ucfirst($normalized)
            ? $formatted
            : str($role)->headline()->toString();
    }
}
