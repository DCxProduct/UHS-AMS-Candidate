{{-- Built-in variables of a template, with what each one means and a sample value. --}}
<div>
    <p style="margin: 0 0 .5rem; font-size: .875rem; font-weight: 600;">{{ $heading }}</p>

    <div style="overflow-x: auto; border: 1px solid rgba(127, 127, 127, .25); border-radius: .5rem;">
        <table style="width: 100%; border-collapse: collapse; font-size: .875rem;">
            <thead>
                <tr style="background: rgba(127, 127, 127, .08); text-align: left;">
                    <th style="padding: .5rem .75rem; font-weight: 600;">{{ $labels['variable'] }}</th>
                    <th style="padding: .5rem .75rem; font-weight: 600;">{{ $labels['meaning'] }}</th>
                    <th style="padding: .5rem .75rem; font-weight: 600;">{{ $labels['sample'] }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($variables as $variable)
                    <tr style="border-top: 1px solid rgba(127, 127, 127, .2);">
                        <td style="padding: .5rem .75rem; white-space: nowrap;"><code>&#123;&#123; {{ $variable['name'] }} &#125;&#125;</code></td>
                        <td style="padding: .5rem .75rem;">{{ $variable['meaning'] }}</td>
                        <td style="padding: .5rem .75rem; opacity: .8;">{{ $variable['sample'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
