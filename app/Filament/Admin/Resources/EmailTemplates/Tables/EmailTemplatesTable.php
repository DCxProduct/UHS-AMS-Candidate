<?php

namespace App\Filament\Admin\Resources\EmailTemplates\Tables;

use App\Filament\Admin\Resources\EmailTemplates\EmailTemplateResource;
use App\Models\EmailTemplate;
use App\Support\LocalizedDate;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('subject')
                    ->label(__('email_templates.fields.subject'))
                    ->state(fn (EmailTemplate $record): string => $record->text('subject', app()->getLocale() === 'km' ? 'km' : 'en'))
                    ->limit(70),

                TextColumn::make('updated_at')
                    ->label(__('email_templates.fields.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
