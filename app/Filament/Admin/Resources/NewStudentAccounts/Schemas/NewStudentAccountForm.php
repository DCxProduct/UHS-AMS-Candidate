<?php

namespace App\Filament\Admin\Resources\NewStudentAccounts\Schemas;

use App\Models\NewStudentAccount;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NewStudentAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        $trim = static fn ($state): string => trim((string) $state);
        $nullableTrim = static fn ($state): ?string => blank(trim((string) $state)) ? null : trim((string) $state);

        return $schema
            ->columns(1)
            ->components([
                Section::make(__('new_student_accounts.sections.account_information'))
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('student_id')
                            ->label(__('new_student_accounts.fields.student_id'))
                            ->required()
                            ->maxLength(100)
                            ->mutateStateForValidationUsing($trim)
                            ->dehydrateStateUsing($trim)
                            ->unique(NewStudentAccount::class, 'student_id', ignoreRecord: true),

                        TextInput::make('email')
                            ->label(__('new_student_accounts.fields.email'))
                            ->email()
                            ->nullable()
                            ->maxLength(150)
                            ->mutateStateForValidationUsing($nullableTrim)
                            ->dehydrateStateUsing($nullableTrim)
                            ->unique(NewStudentAccount::class, 'email', ignoreRecord: true),

                        TextInput::make('phone')
                            ->label(__('new_student_accounts.fields.phone'))
                            ->tel()
                            ->nullable()
                            ->maxLength(30)
                            ->mutateStateForValidationUsing($nullableTrim)
                            ->dehydrateStateUsing($nullableTrim),

                        TextInput::make('first_name_latin')
                            ->label(__('new_student_accounts.fields.first_name_latin'))
                            ->required()
                            ->maxLength(100)
                            ->mutateStateForValidationUsing($trim)
                            ->dehydrateStateUsing($trim),

                        TextInput::make('last_name_latin')
                            ->label(__('new_student_accounts.fields.last_name_latin'))
                            ->required()
                            ->maxLength(100)
                            ->mutateStateForValidationUsing($trim)
                            ->dehydrateStateUsing($trim),

                        Toggle::make('is_active')
                            ->label(__('new_student_accounts.fields.is_active'))
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }
}
