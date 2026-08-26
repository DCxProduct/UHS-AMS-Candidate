<?php

namespace App\Filament\Admin\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use App\Models\SystemUser;
use App\Models\User;
use App\Support\LocalizedDate;
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
            ->modifyQueryUsing(fn (Builder $query): Builder => self::adminActivityQuery($query))
            ->columns([
                TextColumn::make('module')
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
                        'cleared' => 'danger',
                        'downloaded', 'notified' => 'info',
                        default => 'info',
                    }),

                TextColumn::make('actor_name')
                    ->label(__('audit_logs.fields.actor'))
                    ->placeholder(__('audit_logs.values.system'))
                    ->searchable()
                    ->wrap(),

                TextColumn::make('created_at')
                    ->label(__('audit_logs.fields.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::auditDateTime($state)),

                TextColumn::make('ip_address')
                    ->label(__('audit_logs.fields.ip_address'))
                    ->placeholder('-'),
            ])
            ->filters([
                Filter::make('audit_log_filters')
                    ->label(new HtmlString('&nbsp;'))
                    ->schema([
                        Select::make('module')
                            ->label(__('audit_logs.fields.module'))
                            ->options(fn (): array => collect(self::adminActivityQuery(self::scopedQuery())
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

                        Select::make('action')
                            ->label(__('audit_logs.fields.action'))
                            ->options(fn (): array => collect(self::adminActivityQuery(self::scopedQuery())
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

    protected static function scopedQuery(): Builder
    {
        return AuditLog::query();
    }

    protected static function adminActivityQuery(Builder $query): Builder
    {
        return $query
            ->whereNotIn('action', ['login', 'logout'])
            ->whereHasMorph('actor', [User::class, SystemUser::class], function (Builder $query, string $type): void {
                if ($type === User::class) {
                    $query
                        ->where('registration_type', 'admin')
                        ->orWhereHas('roles', fn (Builder $query): Builder => $query->where('name', 'admin'));

                    return;
                }

                $query->where('roles', 'like', '%admin%');
            });
    }
}
