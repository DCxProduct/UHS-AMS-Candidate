<?php

namespace App\Filament\Admin\Resources\SmsTemplates\Tables;

use App\Filament\Admin\Resources\SmsTemplates\SmsTemplateResource;
use App\Models\SmsTemplate;
use App\Support\LocalizedDate;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('body')
                    ->label(__('sms_templates.fields.body'))
                    ->state(fn (SmsTemplate $record): string => $record->text('body', app()->getLocale() === 'km' ? 'km' : 'en'))
                    ->limit(70),

                TextColumn::make('updated_at')
                    ->label(__('sms_templates.fields.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
