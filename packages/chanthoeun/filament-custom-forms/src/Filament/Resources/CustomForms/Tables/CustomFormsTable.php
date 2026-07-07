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
                    ->formatStateUsing(fn ($state): string => self::localeText($state))
                    ->searchable(),

                TextColumn::make('slug')
                    ->label(__('filament-custom-forms::fcf.form.slug'))
                    ->searchable(),

                TextColumn::make('menu_placement')
                    ->label(__('filament-custom-forms::fcf.form.menu_placement'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sidebar' => 'success',
                        'sub_item' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sidebar' => app()->getLocale() === 'km' ? 'ម៉ឺនុយមេ' : 'Sidebar',
                        'sub_item' => app()->getLocale() === 'km' ? 'ម៉ឺនុយរង' : 'Sub Item',
                        default => $state,
                    }),

                TextColumn::make('parent_sidebar')
                    ->label(__('filament-custom-forms::fcf.form.menu_parent'))
                    ->default('—')
                    ->searchable()
                    ->badge()
                    ->alignCenter()
                    ->formatStateUsing(fn ($state): string => self::localeText($state))
                    ->color('info'),

                TextColumn::make('sub_item_type')
                    ->label(__('filament-custom-forms::fcf.form.sub_form'))
                    ->default('—')
                    ->searchable()
                    ->badge()
                    ->alignCenter()
                    ->formatStateUsing(fn ($state): string => self::subItemTypeLabel($state))
                    ->color('warning'),

                TextColumn::make('parentForm.name')
                    ->label(__('filament-custom-forms::fcf.form.form_field'))
                    ->default('—')
                    ->badge()
                    ->hidden()
                    ->alignCenter()
                    ->formatStateUsing(fn ($state): string => self::localeText($state))
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
            ->actions([
                \Filament\Actions\Action::make('edit_template')
                    ->label(app()->getLocale() === 'km' ? 'បង្កើតគំរូឯកសារ' : 'Build Template')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->action(function ($record) {
                        if (! class_exists(\Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::class)) {
                            return;
                        }

                        $template = \Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::firstOrCreate(
                            ['type' => 'custom_form_' . $record->id],
                            [
                                'name' => self::templateName($record->name),
                                'custom_form_id' => $record->id,
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

                        $template->update([
                            'name' => self::templateName($record->name),
                            'custom_form_id' => $record->id,
                            'model_class' => \Chanthoeun\FilamentCustomForms\Models\CustomFormEntry::class,
                        ]);

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

    private static function templateName(mixed $formName): string
    {
        $name = self::decodeText($formName);

        if (is_array($name)) {
            return json_encode([
                'en' => trim(($name['en'] ?? $name['km'] ?? '') . ' Template'),
                'km' => trim(($name['km'] ?? $name['kh'] ?? $name['en'] ?? '') . ' គំរូ'),
                'kh' => trim(($name['km'] ?? $name['kh'] ?? $name['en'] ?? '') . ' គំរូ'),
            ], JSON_UNESCAPED_UNICODE);
        }

        return json_encode([
            'en' => (string) $name . ' Template',
            'km' => (string) $name . ' គំរូ',
            'kh' => (string) $name . ' គំរូ',
        ], JSON_UNESCAPED_UNICODE);
    }

    private static function localeText(mixed $value): string
    {
        $value = self::decodeText($value);

        if (is_array($value)) {
            $locale = app()->getLocale();

            return (string) (
                $value[$locale]
                ?? $value['km']
                ?? $value['kh']
                ?? $value['en']
                ?? collect($value)->first()
                ?? ''
            );
        }

        $text = (string) $value;

        if (app()->getLocale() === 'km') {
            return match ($text) {
                'National Examination Registration' => 'ការចុះឈ្មោះប្រឡងថ្នាក់ជាតិ',
                'Profile' => 'ប្រវត្តិរូប',
                'Sidebar' => 'ម៉ឺនុយមេ',
                'Sub Item' => 'ម៉ឺនុយរង',
                default => $text,
            };
        }

        return $text;
    }

    private static function decodeText(mixed $value): mixed
    {
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_object($value)) {
            return json_decode(json_encode($value), true);
        }

        return $value;
    }

    private static function templateNameFromCustomForm(mixed $formName): string
    {
        $name = self::decodeText($formName);

        $en = is_array($name)
            ? ($name['en'] ?? $name['km'] ?? $name['kh'] ?? '')
            : (string) $name;

        $km = is_array($name)
            ? ($name['km'] ?? $name['kh'] ?? $name['en'] ?? '')
            : (string) $name;

        return json_encode([
            'en' => trim($en . ' Template'),
            'km' => trim($km . ' គំរូ'),
            'kh' => trim($km . ' គំរូ'),
        ], JSON_UNESCAPED_UNICODE);
    }

    private static function subItemTypeLabel(mixed $state): string
    {
        return match ((string) $state) {
            'associate' => app()->getLocale() === 'km' ? 'បរិញ្ញាបត្ររង' : 'Associate',
            'bachelor' => app()->getLocale() === 'km' ? 'បរិញ្ញាបត្រ' : 'Bachelor',
            'master' => app()->getLocale() === 'km' ? 'អនុបណ្ឌិត' : 'Master',
            'phd' => app()->getLocale() === 'km' ? 'បណ្ឌិត' : 'PhD',
            'exam' => app()->getLocale() === 'km' ? 'ការប្រឡង' : 'Exam',
            default => filled($state) ? (string) $state : '—',
        };
    }
}
