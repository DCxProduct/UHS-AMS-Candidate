<?php

namespace App\Support;

use Filament\Forms\Components\DatePicker;

class DatePickerKeyboardInput
{
    public static function placeholder(): string
    {
        return strtolower((string) app()->getLocale()) === 'km'
            ? 'ថ្ងៃ-ខែ-ឆ្នាំ'
            : 'dd-mm-yyyy';
    }

    public static function apply(DatePicker $component): DatePicker
    {
        return $component
            ->displayFormat('d-m-Y')
            ->closeOnDateSelection()
            ->placeholder(static::placeholder())
            ->extraAlpineAttributes([
                'x-init' => <<<'JS'
                    const input = $el.querySelector('.fi-fo-date-time-picker-display-text-input');
                    const picker = $data;

                    if (input && ! input.dataset.keyboardDateInput) {
                        input.dataset.keyboardDateInput = 'true';
                        input.removeAttribute('readonly');
                        input.setAttribute('inputmode', 'numeric');
                        input.setAttribute('autocomplete', 'off');
                        input.setAttribute('maxlength', '10');
                        input.setAttribute('pattern', '[0-9-]*');

                        const initialDate = window.dayjs(input.value, 'DD-MM-YYYY', true);
                        const hasValidInitialDate = input.value === ''
                            || (initialDate.isValid() && ! picker.dateIsDisabled(initialDate));

                        if (!hasValidInitialDate) {
                            input.value = '';
                            picker.clearState?.();
                        }

                        input.dataset.acceptedDateValue = hasValidInitialDate ? input.value : '';
                        input.dataset.acceptedDateCursor = String(input.value.length);

                        input.addEventListener('keydown', (event) => {
                            if (['Backspace', 'Delete', 'Clear'].includes(event.key)) {
                                event.stopPropagation();
                            }
                        }, true);

                        input.addEventListener('input', (event) => {
                            const rawValue = event.target.value;
                            const previousValue = event.target.dataset.acceptedDateValue ?? '';
                            const previousCursorPosition = Number(event.target.dataset.acceptedDateCursor ?? previousValue.length);
                            const cursorPosition = event.target.selectionStart ?? rawValue.length;
                            const digitsBeforeCursor = rawValue
                                .slice(0, cursorPosition)
                                .replace(/\D/g, '')
                                .length;
                            const digits = rawValue.replace(/\D/g, '').slice(0, 8);
                            let value = digits;

                            if (digits.length > 4) {
                                value = `${digits.slice(0, 2)}-${digits.slice(2, 4)}-${digits.slice(4)}`;
                            } else if (digits.length > 2) {
                                value = `${digits.slice(0, 2)}-${digits.slice(2)}`;
                            }

                            const day = digits.length >= 2 ? Number(digits.slice(0, 2)) : null;
                            const month = digits.length >= 4 ? Number(digits.slice(2, 4)) : null;
                            const hasInvalidDayOrMonth = (day !== null && (day < 1 || day > 31))
                                || (month !== null && (month < 1 || month > 12));

                            if (hasInvalidDayOrMonth) {
                                event.target.value = previousValue;
                                event.target.setCustomValidity('');
                                event.target.setAttribute('aria-invalid', 'false');

                                if (document.activeElement === event.target) {
                                    event.target.setSelectionRange(previousCursorPosition, previousCursorPosition);
                                }

                                return;
                            }

                            event.target.value = value;

                            const nextCursorPosition = Math.min(
                                digitsBeforeCursor
                                + (digitsBeforeCursor > 2 ? 1 : 0)
                                + (digitsBeforeCursor > 4 ? 1 : 0),
                                value.length,
                            );

                            if (document.activeElement === event.target) {
                                event.target.setSelectionRange(nextCursorPosition, nextCursorPosition);
                            }

                            if (!/^\d{2}-\d{2}-\d{4}$/.test(value)) {
                                event.target.dataset.acceptedDateValue = value;
                                event.target.dataset.acceptedDateCursor = String(nextCursorPosition);
                                event.target.setCustomValidity('');
                                event.target.setAttribute('aria-invalid', 'false');
                                return;
                            }

                            const date = window.dayjs(value, 'DD-MM-YYYY', true);

                            if (!date.isValid() || picker.dateIsDisabled(date)) {
                                event.target.value = previousValue;
                                event.target.setCustomValidity('');
                                event.target.setAttribute('aria-invalid', 'false');

                                if (document.activeElement === event.target) {
                                    event.target.setSelectionRange(previousCursorPosition, previousCursorPosition);
                                }

                                return;
                            }

                            event.target.dataset.acceptedDateValue = value;
                            event.target.dataset.acceptedDateCursor = String(nextCursorPosition);
                            event.target.setCustomValidity('');
                            event.target.setAttribute('aria-invalid', 'false');
                            picker.focusedDate = window.dayjs(date.format('YYYY-MM-DD'));
                            picker.focusedMonth = date.month();
                            picker.focusedYear = date.year();
                            picker.setupDaysGrid();
                            picker.setState(date);
                            if (picker.isOpen?.()) picker.togglePanelVisibility();
                        }, true);

                        input.addEventListener('blur', (event) => {
                            const value = event.target.value;
                            const date = window.dayjs(value, 'DD-MM-YYYY', true);
                            const isValid = value === '' || (date.isValid() && ! picker.dateIsDisabled(date));

                            event.target.setCustomValidity(isValid ? '' : 'Please enter a valid date in dd-mm-yyyy.');
                            event.target.setAttribute('aria-invalid', isValid ? 'false' : 'true');
                        }, true);
                    }
                JS,
            ]);
    }

    public static function applyMonthName(DatePicker $component): DatePicker
    {
        return $component
            ->displayFormat('d-M-Y')
            ->closeOnDateSelection()
            ->placeholder('dd-Mmm-yyyy')
            ->extraAlpineAttributes([
                'x-init' => <<<'JS'
                    const input = $el.querySelector('.fi-fo-date-time-picker-display-text-input');
                    const picker = $data;

                    if (input && ! input.dataset.keyboardDateInput) {
                        input.dataset.keyboardDateInput = 'true';
                        input.removeAttribute('readonly');
                        input.setAttribute('inputmode', 'text');
                        input.setAttribute('autocomplete', 'off');
                        input.setAttribute('maxlength', '11');
                        input.setAttribute('pattern', '[A-Za-z0-9-]*');

                        const initialDate = window.dayjs(input.value, 'DD-MMM-YYYY', true);
                        const hasValidInitialDate = input.value === ''
                            || (initialDate.isValid() && ! picker.dateIsDisabled(initialDate));

                        if (!hasValidInitialDate) {
                            input.value = '';
                            picker.clearState?.();
                        }

                        input.dataset.acceptedDateValue = hasValidInitialDate ? input.value : '';
                        input.dataset.acceptedDateCursor = String(input.value.length);

                        input.addEventListener('keydown', (event) => {
                            if (['Backspace', 'Delete', 'Clear'].includes(event.key)) {
                                event.stopPropagation();
                            }
                        }, true);

                        input.addEventListener('input', (event) => {
                            const rawValue = event.target.value;
                            const previousValue = event.target.dataset.acceptedDateValue ?? '';
                            const previousCursorPosition = Number(event.target.dataset.acceptedDateCursor ?? previousValue.length);
                            const compactValue = rawValue.replace(/[^a-zA-Z0-9]/g, '').slice(0, 9);
                            const day = compactValue.slice(0, 2);
                            const remainingValue = compactValue.slice(2);

                            if (!/^\d{0,2}$/.test(day)) {
                                event.target.value = previousValue;
                                return;
                            }

                            let month = '';
                            let year = '';
                            let parseFormat = 'DD-MMM-YYYY';
                            const isNumericMonth = /^\d/.test(remainingValue);

                            if (isNumericMonth) {
                                const parts = remainingValue.match(/^(\d{0,2})(\d{0,4})$/);

                                if (!parts) {
                                    event.target.value = previousValue;
                                    return;
                                }

                                month = parts[1];
                                year = parts[2];
                                parseFormat = 'DD-MM-YYYY';
                            } else {
                                const parts = remainingValue.match(/^([a-zA-Z]{0,3})(\d{0,4})$/);

                                if (!parts) {
                                    event.target.value = previousValue;
                                    return;
                                }

                                month = parts[1].length > 0
                                    ? parts[1].charAt(0).toUpperCase() + parts[1].slice(1).toLowerCase()
                                    : '';
                                year = parts[2];
                            }

                            const numericDay = day.length === 2 ? Number(day) : null;
                            const numericMonth = isNumericMonth && month.length === 2
                                ? Number(month)
                                : null;
                            const hasInvalidDayOrMonth = (numericDay !== null && (numericDay < 1 || numericDay > 31))
                                || (numericMonth !== null && (numericMonth < 1 || numericMonth > 12));

                            if (hasInvalidDayOrMonth) {
                                event.target.value = previousValue;
                                event.target.setCustomValidity('');
                                event.target.setAttribute('aria-invalid', 'false');

                                if (document.activeElement === event.target) {
                                    event.target.setSelectionRange(previousCursorPosition, previousCursorPosition);
                                }

                                return;
                            }

                            let value = day;

                            if (remainingValue.length > 0) {
                                value += `-${month}`;
                            }

                            const hasCompleteMonth = isNumericMonth
                                ? month.length === 2
                                : month.length === 3;

                            if (year.length > 0 || (hasCompleteMonth && remainingValue.length > month.length)) {
                                value += `-${year}`;
                            }

                            event.target.value = value;
                            const nextCursorPosition = value.length;

                            if (document.activeElement === event.target) {
                                event.target.setSelectionRange(nextCursorPosition, nextCursorPosition);
                            }

                            const completePattern = isNumericMonth
                                ? /^\d{2}-\d{2}-\d{4}$/
                                : /^\d{2}-[A-Za-z]{3}-\d{4}$/;

                            if (!completePattern.test(value)) {
                                event.target.dataset.acceptedDateValue = value;
                                event.target.dataset.acceptedDateCursor = String(nextCursorPosition);
                                event.target.setCustomValidity('');
                                event.target.setAttribute('aria-invalid', 'false');
                                return;
                            }

                            const date = window.dayjs(value, parseFormat, true);

                            if (!date.isValid() || picker.dateIsDisabled(date)) {
                                event.target.value = previousValue;
                                event.target.setCustomValidity('');
                                event.target.setAttribute('aria-invalid', 'false');

                                if (document.activeElement === event.target) {
                                    event.target.setSelectionRange(previousCursorPosition, previousCursorPosition);
                                }

                                return;
                            }

                            const canonicalValue = date.format('DD-MMM-YYYY');
                            event.target.value = canonicalValue;
                            event.target.dataset.acceptedDateValue = canonicalValue;
                            event.target.dataset.acceptedDateCursor = String(canonicalValue.length);
                            event.target.setCustomValidity('');
                            event.target.setAttribute('aria-invalid', 'false');
                            picker.focusedDate = window.dayjs(date.format('YYYY-MM-DD'));
                            picker.focusedMonth = date.month();
                            picker.focusedYear = date.year();
                            picker.setupDaysGrid();
                            picker.setState(date);
                            if (picker.isOpen?.()) picker.togglePanelVisibility();
                        }, true);

                        input.addEventListener('blur', (event) => {
                            const value = event.target.value;
                            const date = window.dayjs(value, 'DD-MMM-YYYY', true);
                            const isValid = value === '' || (date.isValid() && ! picker.dateIsDisabled(date));

                            event.target.setCustomValidity(isValid ? '' : 'Please enter a valid date in dd-Mmm-yyyy.');
                            event.target.setAttribute('aria-invalid', isValid ? 'false' : 'true');
                        }, true);
                    }
                JS,
            ]);
    }
}
