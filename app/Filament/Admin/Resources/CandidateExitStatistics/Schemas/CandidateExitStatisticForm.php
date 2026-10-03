<?php

namespace App\Filament\Admin\Resources\CandidateExitStatistics\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CandidateExitStatisticForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('candidate_exit_statistics.form.candidate_information'))
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])->schema([
                            Select::make('form_type')
                                ->label(__('candidate_exit_statistics.fields.form_type'))
                                ->options(__('candidate_exit_statistics.options.form_type'))
                                ->native(false)
                                ->searchable(),

                            TextInput::make('academic_year')
                                ->label(__('candidate_exit_statistics.fields.academic_year'))
                                ->maxLength(50),

                            TextInput::make('user_type')
                                ->label(__('candidate_exit_statistics.fields.user_type'))
                                ->maxLength(100),

                            TextInput::make('seat_number')
                                ->label(__('candidate_exit_statistics.fields.seat_number'))
                                ->maxLength(100),

                            TextInput::make('first_name_kh')
                                ->label(__('candidate_exit_statistics.fields.first_name_kh'))
                                ->maxLength(150),

                            TextInput::make('last_name_kh')
                                ->label(__('candidate_exit_statistics.fields.last_name_kh'))
                                ->maxLength(150),

                            TextInput::make('first_name_en')
                                ->label(__('candidate_exit_statistics.fields.first_name_en'))
                                ->maxLength(150),

                            TextInput::make('last_name_en')
                                ->label(__('candidate_exit_statistics.fields.last_name_en'))
                                ->maxLength(150),

                            Select::make('gender')
                                ->label(__('candidate_exit_statistics.fields.gender'))
                                ->options(__('candidate_exit_statistics.options.gender'))
                                ->native(false),

                            TextInput::make('major')
                                ->label(__('candidate_exit_statistics.fields.major'))
                                ->maxLength(150),

                            DatePicker::make('date_of_birth')
                                ->label(__('candidate_exit_statistics.fields.date_of_birth'))
                                ->native(false),

                            Select::make('candidate_status')
                                ->label(__('candidate_exit_statistics.fields.candidate_status'))
                                ->options(__('candidate_exit_statistics.options.candidate_status'))
                                ->default('pending')
                                ->required()
                                ->native(false),

                            DatePicker::make('candidate_reviewed_at')
                                ->label(__('candidate_exit_statistics.fields.candidate_reviewed_at'))
                                ->native(false),
                        ]),
                    ]),
            ]);
    }
}
