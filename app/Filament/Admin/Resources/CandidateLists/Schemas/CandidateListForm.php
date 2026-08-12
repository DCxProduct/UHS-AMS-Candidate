<?php

namespace App\Filament\Admin\Resources\CandidateLists\Schemas;

use App\Models\SystemUser;
use App\Support\UserTypeOptions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CandidateListForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('candidate_lists.sections.user_information'))
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                TextInput::make('username')
                                    ->label(__('candidate_lists.fields.username'))
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(SystemUser::class, 'username', ignoreRecord: true)
                                    ->placeholder(__('candidate_lists.placeholders.username'))
                                    ->rules([
                                        'required',
                                        'regex:/^[a-z0-9_]+$/',
                                    ])
                                    ->validationMessages([
                                        'required' => __('candidate_lists.validation.username_required'),
                                        'regex' => __('candidate_lists.validation.username_regex'),
                                    ])
                                    ->extraInputAttributes([
                                        'autocapitalize' => 'none',
                                        'autocomplete' => 'off',
                                        'oninput' => "this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '')",
                                    ])
                                    ->dehydrateStateUsing(fn ($state): ?string => blank($state) ? null : Str::lower(trim((string) $state))),

                                TextInput::make('email')
                                    ->label(__('candidate_lists.fields.email'))
                                    ->email()
                                    ->nullable()
                                    ->maxLength(150)
                                    ->unique(SystemUser::class, 'email', ignoreRecord: true)
                                    ->placeholder(__('candidate_lists.placeholders.email'))
                                    ->dehydrateStateUsing(fn ($state): ?string => blank($state) ? null : trim((string) $state)),

                                TextInput::make('phone')
                                    ->label(__('candidate_lists.fields.phone'))
                                    ->tel()
                                    ->required()
                                    ->minLength(9)
                                    ->maxLength(10)
                                    ->unique(SystemUser::class, 'phone', ignoreRecord: true)
                                    ->placeholder(__('candidate_lists.placeholders.phone'))
                                    ->rules([
                                        'required',
                                        'regex:/^[0-9]{9,10}$/',
                                    ])
                                    ->validationMessages([
                                        'required' => __('candidate_lists.validation.phone_required'),
                                        'regex' => __('candidate_lists.validation.phone_regex'),
                                        'min' => __('candidate_lists.validation.phone_min'),
                                        'max' => __('candidate_lists.validation.phone_max'),
                                    ])
                                    ->extraInputAttributes([
                                        'inputmode' => 'numeric',
                                        'oninput' => "this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)",
                                    ])
                                    ->dehydrateStateUsing(fn ($state): ?string => blank($state)
                                        ? null
                                        : preg_replace('/[^0-9]/', '', (string) $state)
                                    ),

                                Select::make('candidate_type')
                                    ->label(__('candidate_lists.fields.candidate_type'))
                                    ->options(UserTypeOptions::options())
                                    ->required()
                                    ->searchable()
                                    ->native(false)
                                    ->placeholder(__('candidate_lists.placeholders.candidate_type'))
                                    ->validationMessages([
                                        'required' => __('candidate_lists.validation.candidate_type_required'),
                                    ]),

                                TextInput::make('password')
                                    ->label(__('candidate_lists.fields.password'))
                                    ->password()
                                    ->revealable()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->confirmed()
                                    ->dehydrated(fn ($state): bool => filled($state))
                                    ->dehydrateStateUsing(fn ($state): ?string => filled($state) ? Hash::make($state) : null)
                                    ->maxLength(255)
                                    ->placeholder(fn (string $operation): string => $operation === 'create'
                                        ? __('candidate_lists.placeholders.password_create')
                                        : __('candidate_lists.placeholders.password_edit')
                                    ),

                                TextInput::make('password_confirmation')
                                    ->label(__('candidate_lists.fields.password_confirmation'))
                                    ->password()
                                    ->revealable()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->dehydrated(false)
                                    ->maxLength(255)
                                    ->placeholder(fn (string $operation): string => $operation === 'create'
                                        ? __('candidate_lists.placeholders.password_confirmation_create')
                                        : __('candidate_lists.placeholders.password_confirmation_edit')
                                    ),

                                FileUpload::make('avatar')
                                    ->label(__('candidate_lists.fields.avatar'))
                                    ->placeholder(__('candidate_lists.placeholders.choose_image'))
                                    ->image()
                                    ->imageEditor()
                                    ->disk('public')
                                    ->directory('system-users/avatars')
                                    ->visibility('public')
                                    ->columnSpanFull(),

                                Toggle::make('is_active')
                                    ->label(__('candidate_lists.fields.is_active'))
                                    ->default(true)
                                    ->required(),
                            ]),
                    ]),
            ]);
    }
}
