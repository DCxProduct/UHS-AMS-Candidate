<?php

namespace Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Tables;

use App\Support\LocalizedDate;
use App\Support\FilamentActionPermissions;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource;
use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentTemplateTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament-document-builder::document-builder.labels.template_name'))
                    ->formatStateUsing(fn ($state): string => self::localeText($state))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('filament-document-builder::document-builder.labels.template_type'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'invoice' => 'success',
                        'receipt' => 'warning',
                        'certificate' => 'info',
                        'application' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament-document-builder::document-builder.labels.created_on'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament-document-builder::document-builder.labels.last_updated'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->actions([

                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function localeText(mixed $value): string
    {
        $locale = app()->getLocale();

        if (is_array($value)) {
            return (string) (
                $value[$locale]
                ?? $value['km']
                ?? $value['kh']
                ?? $value['en']
                ?? ''
            );
        }

        $text = (string) $value;

        if (preg_match('/^(\{.*?\})(.*)$/u', $text, $matches)) {
            $decoded = json_decode($matches[1], true);

            if (is_array($decoded)) {
                return trim((
                        $decoded[$locale]
                        ?? $decoded['km']
                        ?? $decoded['kh']
                        ?? $decoded['en']
                        ?? ''
                    ) . ($matches[2] ?? ''));
            }
        }

        return $text;
    }

}
