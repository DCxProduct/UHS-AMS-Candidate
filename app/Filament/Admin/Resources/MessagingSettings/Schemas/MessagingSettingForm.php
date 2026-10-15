<?php

namespace App\Filament\Admin\Resources\MessagingSettings\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MessagingSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->schema([
                        Section::make(__('messaging_settings.sections.mail'))
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 2])->schema([
                                    self::text('mail_host'),
                                    self::text('mail_port')->numeric()->minValue(1)->maxValue(65535),
                                    self::text('mail_username')->email(),
                                    self::secret('mail_password'),
                                    self::text('mail_from_address')->email(),
                                    self::text('mail_from_name'),
                                ]),
                            ]),

                        Section::make(__('messaging_settings.sections.sms'))
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 2])->schema([
                                    self::secret('plasgate_private_key'),
                                    self::secret('plasgate_secret'),
                                    self::text('plasgate_sender')->maxLength(11),
                                    self::text('plasgate_test_phone')->tel(),
                                ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * A plain setting; empty means the .env value is used.
     */
    private static function text(string $column): TextInput
    {
        return TextInput::make($column)
            ->label(__('messaging_settings.fields.'.$column))
            ->placeholder(__('messaging_settings.placeholders.'.$column))
            ->maxLength(255);
    }

    /**
     * A password or key: hidden until the eye button is clicked.
     */
    private static function secret(string $column): TextInput
    {
        return TextInput::make($column)
            ->label(__('messaging_settings.fields.'.$column))
            ->placeholder(__('messaging_settings.placeholders.'.$column))
            ->password()
            ->revealable()
            ->autocomplete('new-password')
            ->maxLength(1000);
    }
}
