<?php

namespace App\Filament\Student\Resources\DocumentRequests\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student_id')
                    ->label(__('app.student_id'))
                    ->searchable(),

                TextColumn::make('name_kh')
                    ->label(__('app.student_name'))
                    ->searchable(),

                TextColumn::make('request_type')
                    ->label(__('app.request_type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'academic_confirmation' => __('app.document_types.academic_confirmation'),
                        'academic_transcript' => __('app.document_types.academic_transcript'),
                        'certificate_of_completion' => __('app.document_types.certificate_of_completion'),
                        'diploma' => __('app.document_types.diploma'),
                        'bachelor_certificate' => __('app.document_types.bachelor_certificate'),
                        'master_certificate' => __('app.document_types.master_certificate'),
                        'other' => __('app.document_types.other'),
                        default => '-',
                    })
                    ->searchable(),

                TextColumn::make('faculty')
                    ->label(__('app.faculty'))
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'medicine' => __('app.faculties.medicine'),
                        'pharmacy' => __('app.faculties.pharmacy'),
                        'dentistry' => __('app.faculties.dentistry'),
                        'public_health' => __('app.faculties.public_health'),
                        'tsmc' => __('app.faculties.tsmc'),
                        'foundation_year' => __('app.faculties.foundation_year'),
                        default => '-',
                    })
                    ->searchable(),

                TextColumn::make('status')
                    ->label(__('app.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending' => __('app.pending'),
                        'approved' => __('app.approved'),
                        'rejected' => __('app.rejected'),
                        default => '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('pdf_file')
                    ->label(__('app.pdf'))
                    ->formatStateUsing(fn ($state): string => $state ? __('app.view_pdf') : '-')
                    ->url(fn ($record): ?string => $record->pdf_file ? asset('storage/' . $record->pdf_file) : null)
                    ->openUrlInNewTab(),

                TextColumn::make('created_at')
                    ->label(__('app.requested_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('app.edit')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('app.delete')),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
