<?php

namespace App\Filament\Admin\Resources\EmailTemplates\Tables;

use App\Filament\Admin\Resources\EmailTemplates\EmailTemplateResource;
use App\Models\EmailTemplate;
use App\Support\CaseInsensitiveSearch;
use App\Support\LocalizedDate;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (EmailTemplate $record): string => EmailTemplateResource::getUrl('edit', ['record' => $record]))
            ->paginated(false)
            ->columns([
                TextColumn::make('key')
                    ->label(__('email_templates.fields.name'))
                    ->formatStateUsing(fn (EmailTemplate $record): string => $record->label())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(function (Builder $query) use ($search): void {
                        $labels = collect(EmailTemplate::TEMPLATES)->mapWithKeys(fn (string $key): array => [$key => (new EmailTemplate(['key' => $key]))->label()])->all();
                        CaseInsensitiveSearch::applyMapped($query, 'key', $search, $labels);
                        $query->orWhere(fn (Builder $names): Builder => CaseInsensitiveSearch::apply($names, 'name', $search));
                    }))
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('action')
                    ->label(__('email_templates.fields.action'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyMapped($query, 'action', $search, EmailTemplate::actionOptions()))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __('email_templates.actions_for.'.$state) : '—')
                    ->placeholder('—')
                    ->badge()
                    ->color('info'),

                TextColumn::make('subject')
                    ->label(__('email_templates.fields.subject'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchText($query, $search, 'subject'))
                    ->state(fn (EmailTemplate $record): string => $record->text('subject'))
                    ->limit(70),

                TextColumn::make('updated_at')
                    ->label(__('email_templates.fields.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyDate($query, $query->getModel()->qualifyColumn('updated_at'), $search)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (EmailTemplate $record): bool => ! $record->isBuiltIn()),
            ]);
    }

    private static function searchText(Builder $query, string $search, string $field): Builder
    {
        return $query->where(function (Builder $query) use ($search, $field): void {
            CaseInsensitiveSearch::apply($query, $field, $search);
            foreach (EmailTemplate::TEMPLATES as $key) {
                $text = (new EmailTemplate(['key' => $key]))->text($field);
                if (mb_stripos($text, $search) !== false) {
                    $query->orWhere(fn (Builder $fallback): Builder => $fallback->where('key', $key)
                        ->where(fn (Builder $empty): Builder => $empty->whereNull($field)->orWhere($field, '')));
                }
            }
        });
    }
}
