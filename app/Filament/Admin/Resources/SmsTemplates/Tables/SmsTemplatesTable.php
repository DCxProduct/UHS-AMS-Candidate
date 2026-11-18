<?php

namespace App\Filament\Admin\Resources\SmsTemplates\Tables;

use App\Filament\Admin\Resources\SmsTemplates\SmsTemplateResource;
use App\Models\SmsTemplate;
use App\Support\CaseInsensitiveSearch;
use App\Support\LocalizedDate;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SmsTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (SmsTemplate $record): string => SmsTemplateResource::getUrl('edit', ['record' => $record]))
            ->paginated(false)
            ->columns([
                TextColumn::make('key')
                    ->label(__('sms_templates.fields.name'))
                    ->formatStateUsing(fn (SmsTemplate $record): string => $record->label())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(function (Builder $query) use ($search): void {
                        $labels = collect(SmsTemplate::TEMPLATES)->mapWithKeys(fn (string $key): array => [$key => (new SmsTemplate(['key' => $key]))->label()])->all();
                        CaseInsensitiveSearch::applyMapped($query, 'key', $search, $labels);
                        $query->orWhere(fn (Builder $names): Builder => CaseInsensitiveSearch::apply($names, 'name', $search));
                    }))
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('action')
                    ->label(__('sms_templates.fields.action'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyMapped($query, 'action', $search, SmsTemplate::actionOptions()))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __('sms_templates.actions_for.'.$state) : '—')
                    ->placeholder('—')
                    ->badge()
                    ->color('info'),

                TextColumn::make('body')
                    ->label(__('sms_templates.fields.body'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchText($query, $search, 'body'))
                    ->state(fn (SmsTemplate $record): string => $record->text('body'))
                    ->limit(70),

                TextColumn::make('updated_at')
                    ->label(__('sms_templates.fields.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyDate($query, $query->getModel()->qualifyColumn('updated_at'), $search)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (SmsTemplate $record): bool => ! $record->isBuiltIn()),
            ]);
    }

    private static function searchText(Builder $query, string $search, string $field): Builder
    {
        return $query->where(function (Builder $query) use ($search, $field): void {
            CaseInsensitiveSearch::apply($query, $field, $search);
            foreach (SmsTemplate::TEMPLATES as $key) {
                $text = (new SmsTemplate(['key' => $key]))->text($field);
                if (mb_stripos($text, $search) !== false) {
                    $query->orWhere(fn (Builder $fallback): Builder => $fallback->where('key', $key)
                        ->where(fn (Builder $empty): Builder => $empty->whereNull($field)->orWhere($field, '')));
                }
            }
        });
    }
}
