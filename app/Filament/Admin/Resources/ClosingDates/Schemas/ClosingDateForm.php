<?php

namespace App\Filament\Admin\Resources\ClosingDates\Schemas;

use App\Models\ClosingDate;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ClosingDateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                Section::make('Closing Date Information')
                    ->description('Manage start date and end date for each dynamic form.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->placeholder('Enter name feature')
                            ->required()
                            ->maxLength(150)
                            ->columnSpan(12),

                        Select::make('type')
                            ->label('Student Application')
                            ->placeholder('Select dynamic form')
                            ->options(fn (): array => ClosingDate::typeOptions())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(12),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'not_open' => 'Not Open',
                                'open' => 'Open',
                                'closed' => 'Closed',
                            ])
                            ->placeholder('Select status')
                            ->searchable()
                            ->required()
                            ->columnSpan(12),

                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->format('Y-m-d')
                            ->placeholder('Select start date')
                            ->required()
                            ->live()
                            ->maxDate(fn (Get $get) => $get('end_date') ?: null)
                            ->rules([
                                'required',
                                'date',
                                fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                    $endDate = $get('end_date');

                                    if (! $value || ! $endDate) {
                                        return;
                                    }

                                    if (Carbon::parse($value)->gt(Carbon::parse($endDate))) {
                                        $fail('Start date must be before or equal to end date.');
                                    }
                                },
                            ])
                            ->columnSpan(6),

                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->format('Y-m-d')
                            ->placeholder('Select end date')
                            ->required()
                            ->live()
                            ->minDate(fn (Get $get) => $get('start_date') ?: null)
                            ->rules([
                                'required',
                                'date',
                                fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                    $startDate = $get('start_date');

                                    if (! $value || ! $startDate) {
                                        return;
                                    }

                                    if (Carbon::parse($value)->lt(Carbon::parse($startDate))) {
                                        $fail('End date must be after or equal to start date.');
                                    }
                                },
                            ])
                            ->columnSpan(6),
                    ])
                    ->columns(12)
                    ->columnSpan(12),
            ]);
    }
}
