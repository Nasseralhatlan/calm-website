<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0; padding:0; background-color:#F8F8F8; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F8F8F8; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:18px; overflow:hidden; box-shadow:0 8px 24px rgba(0,0,0,0.06);">

                    {{-- Brand header --}}
                    <tr>
                        <td style="background-color:#F88379; padding:24px 28px;" align="left">
                            <span style="font-size:22px; font-weight:800; color:#ffffff; letter-spacing:0.5px;">Calm</span>
                            <span style="font-size:16px; color:#ffffff; opacity:0.85; padding-left:6px;">كالم</span>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:28px;">
                            <div style="display:inline-block; font-size:11px; font-weight:700; letter-spacing:0.6px; text-transform:uppercase; color:#F88379; background-color:#fff4f3; padding:4px 10px; border-radius:8px; margin-bottom:14px;">Internal alert</div>
                            <h1 style="margin:0 0 6px; font-size:20px; line-height:1.3; color:#222222; font-weight:700;">{{ $heading }}</h1>

                            @foreach ($lines as $i => $line)
                                @if ($i === 0)
                                    <p style="margin:14px 0 18px; font-size:15px; line-height:1.5; color:#717171;">{{ $line }}</p>
                                @else
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px;">
                                        <tr>
                                            <td style="padding:12px 16px; font-size:15px; color:#222222; background-color:#fafafa; border:1px solid #f0f0f0; border-radius:12px;">{{ $line }}</td>
                                        </tr>
                                    </table>
                                @endif
                            @endforeach
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:18px 28px; border-top:1px solid #f0f0f0;">
                            <p style="margin:0; font-size:12px; color:#9ca3af;">Automated internal alert from <strong style="color:#F88379;">Calm</strong> · كالم. No reply needed.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
