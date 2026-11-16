<?php

namespace App\Filament\Admin\Resources\NewStudentAccounts\Schemas;

use App\Support\LocalizedDate;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NewStudentAccountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('new_student_accounts.sections.account_information'))
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextEntry::make('student_id')
                            ->label(__('new_student_accounts.fields.student_id')),
                        TextEntry::make('email')
                            ->label(__('new_student_accounts.fields.email'))
                            ->placeholder('-'),
                        TextEntry::make('phone')
                            ->label(__('new_student_accounts.fields.phone'))
                            ->placeholder('-'),
                        TextEntry::make('first_name_latin')
                            ->label(__('new_student_accounts.fields.first_name_latin')),
                        TextEntry::make('last_name_latin')
                            ->label(__('new_student_accounts.fields.last_name_latin')),
                        IconEntry::make('is_active')
                            ->label(__('new_student_accounts.fields.is_active'))
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->label(__('new_student_accounts.fields.created_at'))
                            ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),
                        TextEntry::make('updated_at')
                            ->label(__('new_student_accounts.fields.updated_at'))
                            ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),
                    ]),
            ]);
    }
}
