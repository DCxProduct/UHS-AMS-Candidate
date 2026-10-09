<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $headerTitle }}</title>
</head>
<body style="margin: 0; padding: 24px 12px; background: #f4f4f5; font-family: Arial, 'Battambang', 'Khmer OS Battambang', sans-serif; color: #18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px;">
                    <tr>
                        <td align="center" style="padding: 8px 0 20px; font-size: 20px; font-weight: 700; color: #1e40af;">
                            {{-- Embedded in the email, so it shows even when the site is not public. --}}
                            @if ($logoPath && isset($message))
                                <img src="{{ $message->embed($logoPath) }}" alt="{{ $headerTitle }}" style="display: block; max-width: 220px; max-height: 90px; margin: 0 auto 10px; border: 0;">
                            @endif
                            {{ $headerTitle }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px; background: #ffffff; border: 1px solid #e4e4e7; border-radius: 10px; font-size: 15px; line-height: 1.6;">
                            {!! $body !!}

                            <hr style="margin: 28px 0 16px; border: 0; border-top: 1px solid #e4e4e7;">
                            <p style="margin: 0; font-size: 12px; color: #71717a; word-break: break-all;">
                                {{ $subcopy }}
                                <a href="{{ $url }}" style="color: #1e40af;">{{ $url }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding: 20px 0; font-size: 12px; color: #a1a1aa;">
                            © {{ date('Y') }} {{ $headerTitle }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
