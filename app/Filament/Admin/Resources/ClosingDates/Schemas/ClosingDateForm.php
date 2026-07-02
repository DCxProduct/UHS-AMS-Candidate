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
                Section::make(__('closing_dates.closing_date_information'))
                    ->description(__('closing_dates.closing_date_information_description'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('closing_dates.name'))
                            ->placeholder(__('closing_dates.name_placeholder'))
                            ->required()
                            ->maxLength(150)
                            ->validationMessages([
                                'required' => __('closing_dates.name_required'),
                            ])
                            ->columnSpan(12),

                        Select::make('type')
                            ->label(__('closing_dates.student_application'))
                            ->placeholder(__('closing_dates.type_placeholder'))
                            ->options(fn (): array => collect(ClosingDate::typeOptions())
                                ->mapWithKeys(fn ($label, $value): array => [
                                    $value => self::localeText($label),
                                ])
                                ->toArray()
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => __('closing_dates.type_required'),
                            ])
                            ->columnSpan(12),

                        Select::make('status')
                            ->label(__('closing_dates.status'))
                            ->placeholder(__('closing_dates.status_placeholder'))
                            ->options([
                                'not_open' => __('closing_dates.statuses.not_open'),
                                'open' => __('closing_dates.statuses.open'),
                                'closed' => __('closing_dates.statuses.closed'),
                            ])
                            ->searchable()
                            ->required()
                            ->validationMessages([
                                'required' => __('closing_dates.status_required'),
                            ])
                            ->columnSpan(12),

                        DatePicker::make('start_date')
                            ->label(__('closing_dates.start_date'))
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->format('Y-m-d')
                            ->placeholder(__('closing_dates.start_date_placeholder'))
                            ->required()
                            ->live()
                            ->maxDate(fn (Get $get) => $get('end_date') ?: null)
                            ->rules([
                                'required',
                                'date',
                                fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get): void {
                                    $endDate = $get('end_date');

                                    if (! $value || ! $endDate) {
                                        return;
                                    }

                                    if (Carbon::parse($value)->gt(Carbon::parse($endDate))) {
                                        $fail(__('closing_dates.start_date_before_or_equal_end_date'));
                                    }
                                },
                            ])
                            ->validationMessages([
                                'required' => __('closing_dates.start_date_required'),
                                'date' => __('closing_dates.start_date_valid'),
                            ])
                            ->columnSpan(6),

                        DatePicker::make('end_date')
                            ->label(__('closing_dates.end_date'))
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->format('Y-m-d')
                            ->placeholder(__('closing_dates.end_date_placeholder'))
                            ->required()
                            ->live()
                            ->minDate(fn (Get $get) => $get('start_date') ?: null)
                            ->rules([
                                'required',
                                'date',
                                fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get): void {
                                    $startDate = $get('start_date');

                                    if (! $value || ! $startDate) {
                                        return;
                                    }

                                    if (Carbon::parse($value)->lt(Carbon::parse($startDate))) {
                                        $fail(__('closing_dates.end_date_after_or_equal_start_date'));
                                    }
                                },
                            ])
                            ->validationMessages([
                                'required' => __('closing_dates.end_date_required'),
                                'date' => __('closing_dates.end_date_valid'),
                            ])
                            ->columnSpan(6),
                    ])
                    ->columns(12)
                    ->columnSpan(12),
            ]);
    }

    private static function localeText(mixed $value): string
    {
        $locale = app()->getLocale();

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return (string) (
                    $decoded[$locale]
                    ?? $decoded['km']
                    ?? $decoded['kh']
                    ?? $decoded['en']
                    ?? ''
                );
            }
        }

        if (is_array($value)) {
            return (string) (
                $value[$locale]
                ?? $value['km']
                ?? $value['kh']
                ?? $value['en']
                ?? ''
            );
        }

        return (string) $value;
    }
}
