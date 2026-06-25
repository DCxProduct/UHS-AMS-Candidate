<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class CustomFormsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-custom-forms::fcf.form.name'))
                    ->formatStateUsing(fn ($state): string => self::englishText($state))
                    ->searchable(),
                TextColumn::make('slug')
                    ->label(__('filament-custom-forms::fcf.form.slug'))
                    ->searchable(),
                TextColumn::make('menu_placement')
                    ->label('Placement')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sidebar' => 'success',
                        'sub_item' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sidebar' => 'Sidebar',
                        'sub_item' => 'Sub Item',
                        default => $state,
                    }),
                TextColumn::make('parent_sidebar')
                    ->label('Parent Sidebar')
                    ->default('—')
                    ->searchable()
                    ->badge()
                    ->alignCenter()
                    ->color('info'),
                TextColumn::make('sub_item_type')
                    ->label('Sub Item Type')
                    ->default('—')
                    ->searchable()
                    ->badge()
                    ->alignCenter()
                    ->color('warning'),
                TextColumn::make('parentForm.name')
                    ->label('Form Type Field')
                    ->default('—')
                    ->badge()
                    ->alignCenter()
                    ->formatStateUsing(fn ($state): string => self::englishText($state))
                    ->color('info'),
                IconColumn::make('is_active')
                    ->label(__('filament-custom-forms::fcf.form.is_active'))
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label(__('filament-custom-forms::fcf.general.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('filament-custom-forms::fcf.general.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label(__('filament-custom-forms::fcf.general.deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([ // Standard way is actions()
                \Filament\Actions\Action::make('edit_template')
                    ->label('Build Template')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->action(function ($record) {
                        if (!class_exists(\Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::class)) {
                            return;
                        }

                        $template = \Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::firstOrCreate(
                            ['type' => 'custom_form_' . $record->id],
                            [
                                'name' => $record->name . ' Template',
                                'model_class' => \Chanthoeun\FilamentCustomForms\Models\CustomFormEntry::class,
                                'content' => '',
                                'page_settings' => [
                                    'format' => 'a4',
                                    'orientation' => 'portrait',
                                    'margin_left' => 15,
                                    'margin_right' => 15,
                                    'margin_top' => 15,
                                    'margin_bottom' => 15,
                                ],
                            ]
                        );

                        if (class_exists(\App\Filament\Resources\DocumentTemplateResource::class)) {
                            $url = \App\Filament\Resources\DocumentTemplateResource::getUrl('edit', ['record' => $template]);
                        } else {
                            $url = \Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource::getUrl('edit', ['record' => $template]);
                        }

                        return redirect($url);
                    }),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function englishText(mixed $value): string
    {
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            return (string) ($value['en'] ?? $value['km'] ?? '');
        }

        return (string) $value;
    }
}
