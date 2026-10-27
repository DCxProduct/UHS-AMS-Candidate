<td colspan="{{ count($columns) + 2 }}" class="fi-ta-cell">
    <div data-payment-status-summary role="status" aria-live="polite" style="padding: 1rem 1.5rem;">
        <dl aria-label="{{ __('payment_status_lists.summary.heading') }}" style="display: flex; flex-wrap: wrap; gap: 1rem 3rem; margin: 0;">
            @foreach (['total' => 'gray', 'paid' => 'success', 'unpaid' => 'warning'] as $key => $color)
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <dt>{{ __('payment_status_lists.summary.'.$key) }}</dt>
                    <dd style="margin: 0;" data-summary-{{ $key }}="{{ $counts[$key] }}">
                        <x-filament::badge :color="$color">
                            {{ \App\Support\LocalizedNumber::digits(number_format($counts[$key])) }}
                        </x-filament::badge>
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>
</td>
