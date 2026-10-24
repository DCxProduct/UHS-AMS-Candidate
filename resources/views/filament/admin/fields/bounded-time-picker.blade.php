@php
    $timeFieldName = $getName();
    $siblingTimeFieldName = $timeFieldName === 'from_time' ? 'to_time' : 'from_time';
    $siblingTimeStatePath = preg_replace(
        '/' . preg_quote($timeFieldName, '/') . '$/',
        $siblingTimeFieldName,
        $getStatePath(),
    );
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        style="position: relative; width: 100%;"
        x-data="{
            time: $wire.$entangle(@js($getStatePath())).live,
            siblingTime: $wire.$entangle(@js($siblingTimeStatePath)).live,
            timeFieldName: @js($timeFieldName),
            open: false,
            hours: Array.from({ length: 12 }, (_, index) => index + 1),
            minutes: Array.from({ length: 60 }, (_, index) => index),

            get siblingMinutes() {
                const [hour, minute] = String(this.siblingTime ?? '').split(':').map(Number);

                if (!Number.isInteger(hour) || !Number.isInteger(minute)) return null;

                return (hour * 60) + minute;
            },

            get parts() {
                let value = String(this.time ?? '').trim();

                if (value.includes(' ')) value = value.split(' ').pop();
                if (value.includes('T')) value = value.split('T').pop();

                const [hour = '00', minute = '00'] = value.split(':');
                const hour24 = Number.parseInt(hour, 10) || 0;

                return {
                    hour: ((hour24 + 11) % 12) + 1,
                    minute: Number.parseInt(minute, 10) || 0,
                    period: hour24 >= 12 ? 'PM' : 'AM',
                };
            },

            get displayText() {
                if (!this.time) return @js(__('payments.placeholders.' . $getName()));

                const parts = this.parts;
                return `${this.khmer(String(parts.hour).padStart(2, '0'))}:${this.khmer(String(parts.minute).padStart(2, '0'))} ${this.periodLabel(parts.period)}`;
            },

            khmer(value) {
                const digits = ['០', '១', '២', '៣', '៤', '៥', '៦', '៧', '៨', '៩'];
                return String(value).replace(/[0-9]/g, digit => digits[Number(digit)]);
            },

            periodLabel(period) {
                return @js(app()->getLocale()) === 'km'
                    ? (period === 'AM' ? 'ព្រឹក' : 'ល្ងាច')
                    : period;
            },

            setTime(hour = this.parts.hour, minute = this.parts.minute, period = this.parts.period) {
                const hour24 = (hour % 12) + (period === 'PM' ? 12 : 0);
                this.time = `${String(hour24).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
            },

            toMinutes(hour, minute, period) {
                return (((hour % 12) + (period === 'PM' ? 12 : 0)) * 60) + minute;
            },

            isTimeAllowed(candidateMinutes) {
                if (this.siblingMinutes === null) return true;

                return this.timeFieldName === 'from_time'
                    ? candidateMinutes <= this.siblingMinutes
                    : candidateMinutes >= this.siblingMinutes;
            },
        }"
        x-on:click.outside="open = false"
        x-on:keydown.escape.window="open = false"
    >
        <button
            type="button"
            class="fi-input"
            style="display: flex; width: 100%; min-height: 42px; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08); text-align: start;"
            x-on:click="open = ! open"
            x-bind:aria-expanded="open"
        >
            <span
                style="color: #9ca3af;"
                x-bind:style="time ? 'color: #111827;' : 'color: #9ca3af;'"
                x-text="displayText"
            ></span>
            <svg width="20" height="20" style="width: 20px; height: 20px; flex: none; color: #9ca3af;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5v5l3 1.5" />
            </svg>
        </button>

        <div
            x-cloak
            x-show="open"
            x-transition.origin.top.left
            style="position: absolute; z-index: 1000; top: calc(100% + 8px); left: 0; width: min(360px, calc(100vw - 32px)); padding: 10px; border: 1px solid #e5e7eb; border-radius: 12px; background: white; box-shadow: 0 12px 28px rgba(0, 0, 0, 0.16);"
        >
            <div style="display: flex; flex-direction: row; align-items: flex-start; gap: 8px; width: 100%;">
            <div style="flex: 1 1 0; min-width: 0;">
                <div style="margin-bottom: 4px; text-align: center; font-size: 12px; font-weight: 600; color: #6b7280;">ម៉ោង</div>
                <div style="height: 144px; overflow-y: auto; overscroll-behavior: contain; border: 1px solid #e5e7eb; border-radius: 8px; padding: 3px;" role="listbox" aria-label="Hours">
                    <template x-for="hour in hours" x-bind:key="hour">
                        <button
                            type="button"
                            style="display: block; width: 100%; border: 0; border-radius: 6px; padding: 3px 6px; text-align: center; font-size: 14px; line-height: 20px; cursor: pointer; background: transparent;"
                            x-bind:disabled="! isTimeAllowed(toMinutes(hour, parts.minute, parts.period))"
                            x-bind:aria-disabled="! isTimeAllowed(toMinutes(hour, parts.minute, parts.period))"
                            x-bind:style="parts.hour === hour ? 'display: block; width: 100%; border: 0; border-radius: 6px; padding: 3px 6px; text-align: center; font-size: 14px; line-height: 20px; cursor: pointer; background: #2563eb; color: white; font-weight: 600;' : (isTimeAllowed(toMinutes(hour, parts.minute, parts.period)) ? 'display: block; width: 100%; border: 0; border-radius: 6px; padding: 3px 6px; text-align: center; font-size: 14px; line-height: 20px; cursor: pointer; background: transparent; color: #111827;' : 'display: block; width: 100%; border: 0; border-radius: 6px; padding: 3px 6px; text-align: center; font-size: 14px; line-height: 20px; cursor: not-allowed; background: transparent; color: #9ca3af; opacity: 0.5;')"
                            x-on:click="setTime(hour)"
                            x-text="khmer(String(hour).padStart(2, '0'))"
                            role="option"
                            x-bind:aria-selected="parts.hour === hour"
                        ></button>
                    </template>
                </div>
            </div>

            <div style="flex: 1 1 0; min-width: 0;">
                <div style="margin-bottom: 4px; text-align: center; font-size: 12px; font-weight: 600; color: #6b7280;">នាទី</div>
                <div style="height: 144px; overflow-y: auto; overscroll-behavior: contain; border: 1px solid #e5e7eb; border-radius: 8px; padding: 3px;" role="listbox" aria-label="Minutes">
                    <template x-for="minute in minutes" x-bind:key="minute">
                        <button
                            type="button"
                            style="display: block; width: 100%; border: 0; border-radius: 6px; padding: 3px 6px; text-align: center; font-size: 14px; line-height: 20px; cursor: pointer; background: transparent;"
                            x-bind:disabled="! isTimeAllowed(toMinutes(parts.hour, minute, parts.period))"
                            x-bind:aria-disabled="! isTimeAllowed(toMinutes(parts.hour, minute, parts.period))"
                            x-bind:style="parts.minute === minute ? 'display: block; width: 100%; border: 0; border-radius: 6px; padding: 3px 6px; text-align: center; font-size: 14px; line-height: 20px; cursor: pointer; background: #2563eb; color: white; font-weight: 600;' : (isTimeAllowed(toMinutes(parts.hour, minute, parts.period)) ? 'display: block; width: 100%; border: 0; border-radius: 6px; padding: 3px 6px; text-align: center; font-size: 14px; line-height: 20px; cursor: pointer; background: transparent; color: #111827;' : 'display: block; width: 100%; border: 0; border-radius: 6px; padding: 3px 6px; text-align: center; font-size: 14px; line-height: 20px; cursor: not-allowed; background: transparent; color: #9ca3af; opacity: 0.5;')"
                            x-on:click="setTime(parts.hour, minute)"
                            x-text="khmer(String(minute).padStart(2, '0'))"
                            role="option"
                            x-bind:aria-selected="parts.minute === minute"
                        ></button>
                    </template>
                </div>
            </div>

            <div style="flex: 1 1 0; min-width: 0;">
                <div style="margin-bottom: 4px; text-align: center; font-size: 12px; font-weight: 600; color: #6b7280;" x-text="@js(app()->getLocale()) === 'km' ? 'ព្រឹក / ល្ងាច' : 'AM / PM'"></div>
                <div style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 4px;" role="listbox" aria-label="AM or PM">
                    <template x-for="period in ['AM', 'PM']" x-bind:key="period">
                        <button
                            type="button"
                            style="display: block; width: 100%; border: 0; border-radius: 6px; padding: 8px; text-align: center; font-size: 14px; cursor: pointer; background: transparent;"
                            x-bind:disabled="! isTimeAllowed(toMinutes(parts.hour, parts.minute, period))"
                            x-bind:aria-disabled="! isTimeAllowed(toMinutes(parts.hour, parts.minute, period))"
                            x-bind:style="parts.period === period ? 'display: block; width: 100%; border: 0; border-radius: 6px; padding: 8px; text-align: center; font-size: 14px; cursor: pointer; background: #2563eb; color: white; font-weight: 600;' : (isTimeAllowed(toMinutes(parts.hour, parts.minute, period)) ? 'display: block; width: 100%; border: 0; border-radius: 6px; padding: 8px; text-align: center; font-size: 14px; cursor: pointer; background: transparent; color: #111827;' : 'display: block; width: 100%; border: 0; border-radius: 6px; padding: 8px; text-align: center; font-size: 14px; cursor: not-allowed; background: transparent; color: #9ca3af; opacity: 0.5;')"
                            x-on:click="setTime(parts.hour, parts.minute, period)"
                            x-text="periodLabel(period)"
                            role="option"
                            x-bind:aria-selected="parts.period === period"
                        ></button>
                    </template>
                </div>
            </div>
            </div>
        </div>
    </div>
</x-dynamic-component>
