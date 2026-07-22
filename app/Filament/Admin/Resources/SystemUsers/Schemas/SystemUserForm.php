<?php

namespace App\Filament\Admin\Resources\SystemUsers\Schemas;

use App\Models\SystemUser;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class SystemUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('system_users.sections.system_user_information'))
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                TextInput::make('username')
                                    ->label(__('system_users.fields.username'))
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(SystemUser::class, 'username', ignoreRecord: true)
                                    ->placeholder(__('system_users.placeholders.username'))
                                    ->dehydrateStateUsing(fn ($state): ?string => blank($state) ? null : trim((string) $state)),

                                TextInput::make('email')
                                    ->label(__('system_users.fields.email'))
                                    ->email()
                                    ->nullable()
                                    ->maxLength(150)
                                    ->unique(SystemUser::class, 'email', ignoreRecord: true)
                                    ->placeholder(__('system_users.placeholders.email'))
                                    ->dehydrateStateUsing(fn ($state): ?string => blank($state) ? null : trim((string) $state)),

                                TextInput::make('phone')
                                    ->label(__('system_users.fields.phone'))
                                    ->tel()
                                    ->required()
                                    ->minLength(9)
                                    ->maxLength(10)
                                    ->columnSpanFull()
                                    ->unique(SystemUser::class, 'phone', ignoreRecord: true)
                                    ->placeholder(__('system_users.placeholders.phone'))
                                    ->rules([
                                        'required',
                                        'regex:/^[0-9]{9,10}$/',
                                    ])
                                    ->validationMessages([
                                        'required' => __('system_users.validation.phone_required'),
                                        'regex' => __('system_users.validation.phone_regex'),
                                        'min' => __('system_users.validation.phone_min'),
                                        'max' => __('system_users.validation.phone_max'),
                                    ])
                                    ->extraInputAttributes([
                                        'inputmode' => 'numeric',
                                        'oninput' => "this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)",
                                    ])
                                    ->dehydrateStateUsing(fn ($state): ?string => blank($state)
                                        ? null
                                        : preg_replace('/[^0-9]/', '', (string) $state)
                                    ),

                                TextInput::make('password')
                                    ->label(__('system_users.fields.password'))
                                    ->password()
                                    ->revealable()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->confirmed()
                                    ->dehydrated(fn ($state): bool => filled($state))
                                    ->dehydrateStateUsing(fn ($state): ?string => filled($state) ? Hash::make($state) : null)
                                    ->maxLength(255)
                                    ->placeholder(fn (string $operation): string => $operation === 'create'
                                        ? __('system_users.placeholders.password_create')
                                        : __('system_users.placeholders.password_edit')
                                    ),

                                TextInput::make('password_confirmation')
                                    ->label(__('system_users.fields.password_confirmation'))
                                    ->password()
                                    ->revealable()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->dehydrated(false)
                                    ->maxLength(255)
                                    ->placeholder(fn (string $operation): string => $operation === 'create'
                                        ? __('system_users.placeholders.password_confirmation_create')
                                        : __('system_users.placeholders.password_confirmation_edit')
                                    ),

                                FileUpload::make('avatar')
                                    ->label(__('system_users.fields.avatar'))
                                    ->placeholder(__('system_users.placeholders.choose_image'))
                                    ->image()
                                    ->imageEditor()
                                    ->disk('public')
                                    ->directory('system-users/avatars')
                                    ->visibility('public')
                                    ->columnSpanFull(),

                                Toggle::make('is_active')
                                    ->label(__('system_users.fields.is_active'))
                                    ->default(true)
                                    ->required(),
                            ]),
                    ]),
            ]);
    }
}
