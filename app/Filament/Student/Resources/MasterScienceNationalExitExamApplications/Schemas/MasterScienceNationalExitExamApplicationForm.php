<?php

namespace App\Filament\Student\Resources\MasterScienceNationalExitExamApplications\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MasterScienceNationalExitExamApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->numeric(),
                TextInput::make('application_no'),
                TextInput::make('full_name_kh')
                    ->required(),
                TextInput::make('full_name_latin'),
                TextInput::make('gender'),
                TextInput::make('nationality'),
                DatePicker::make('date_of_birth'),
                Textarea::make('birth_place')
                    ->columnSpanFull(),
                TextInput::make('birth_village_group'),
                TextInput::make('birth_commune'),
                TextInput::make('birth_district'),
                TextInput::make('birth_province'),
                TextInput::make('candidate_from'),
                TextInput::make('completed_study_level'),
                TextInput::make('completed_study_at'),
                TextInput::make('academic_year'),
                TextInput::make('exam_class'),
                TextInput::make('exam_session_date'),
                TextInput::make('phone')
                    ->tel(),
                Textarea::make('current_address')
                    ->columnSpanFull(),
                Textarea::make('contact_address')
                    ->columnSpanFull(),
                TextInput::make('contact_phone')
                    ->tel(),
                TextInput::make('education_level'),
                TextInput::make('school_name'),
                TextInput::make('school_location'),
                TextInput::make('foreign_language'),
                TextInput::make('marital_status')
                    ->required()
                    ->default('single'),
                TextInput::make('spouse_name'),
                DatePicker::make('spouse_date_of_birth'),
                TextInput::make('spouse_nationality'),
                TextInput::make('spouse_occupation'),
                TextInput::make('father_name'),
                TextInput::make('father_status'),
                TextInput::make('father_age')
                    ->numeric(),
                TextInput::make('father_birth_place'),
                TextInput::make('father_nationality'),
                TextInput::make('father_occupation'),
                TextInput::make('mother_name'),
                TextInput::make('mother_status'),
                TextInput::make('mother_age')
                    ->numeric(),
                TextInput::make('mother_birth_place'),
                TextInput::make('mother_nationality'),
                TextInput::make('mother_occupation'),
                TextInput::make('photo'),
                TextInput::make('payment_amount')
                    ->required()
                    ->numeric()
                    ->default(100000),
                TextInput::make('status')
                    ->required()
                    ->default('submitted'),
                DateTimePicker::make('submitted_at'),
                TextInput::make('reviewed_by')
                    ->numeric(),
                DateTimePicker::make('reviewed_at'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
