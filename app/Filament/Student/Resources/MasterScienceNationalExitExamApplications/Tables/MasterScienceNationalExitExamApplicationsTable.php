<?php

namespace App\Filament\Student\Resources\MasterScienceNationalExitExamApplications\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MasterScienceNationalExitExamApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('application_no')
                    ->searchable(),
                TextColumn::make('full_name_kh')
                    ->searchable(),
                TextColumn::make('full_name_latin')
                    ->searchable(),
                TextColumn::make('gender')
                    ->searchable(),
                TextColumn::make('nationality')
                    ->searchable(),
                TextColumn::make('date_of_birth')
                    ->date()
                    ->sortable(),
                TextColumn::make('birth_village_group')
                    ->searchable(),
                TextColumn::make('birth_commune')
                    ->searchable(),
                TextColumn::make('birth_district')
                    ->searchable(),
                TextColumn::make('birth_province')
                    ->searchable(),
                TextColumn::make('candidate_from')
                    ->searchable(),
                TextColumn::make('completed_study_level')
                    ->searchable(),
                TextColumn::make('completed_study_at')
                    ->searchable(),
                TextColumn::make('academic_year')
                    ->searchable(),
                TextColumn::make('exam_class')
                    ->searchable(),
                TextColumn::make('exam_session_date')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('contact_phone')
                    ->searchable(),
                TextColumn::make('education_level')
                    ->searchable(),
                TextColumn::make('school_name')
                    ->searchable(),
                TextColumn::make('school_location')
                    ->searchable(),
                TextColumn::make('foreign_language')
                    ->searchable(),
                TextColumn::make('marital_status')
                    ->searchable(),
                TextColumn::make('spouse_name')
                    ->searchable(),
                TextColumn::make('spouse_date_of_birth')
                    ->date()
                    ->sortable(),
                TextColumn::make('spouse_nationality')
                    ->searchable(),
                TextColumn::make('spouse_occupation')
                    ->searchable(),
                TextColumn::make('father_name')
                    ->searchable(),
                TextColumn::make('father_status')
                    ->searchable(),
                TextColumn::make('father_age')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('father_birth_place')
                    ->searchable(),
                TextColumn::make('father_nationality')
                    ->searchable(),
                TextColumn::make('father_occupation')
                    ->searchable(),
                TextColumn::make('mother_name')
                    ->searchable(),
                TextColumn::make('mother_status')
                    ->searchable(),
                TextColumn::make('mother_age')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('mother_birth_place')
                    ->searchable(),
                TextColumn::make('mother_nationality')
                    ->searchable(),
                TextColumn::make('mother_occupation')
                    ->searchable(),
                TextColumn::make('photo')
                    ->searchable(),
                TextColumn::make('payment_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('reviewed_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
