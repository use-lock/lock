<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $realmName }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: {{ $background }}; color: {{ $foreground }}; font-family: {{ $fontFamily }};">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: {{ $background }};">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 520px;">
                    <tr>
                        <td align="center" style="padding-bottom: 24px; font-size: 24px; color: {{ $foreground }};">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ $realmName }}" style="max-width: 280px; height: auto; border: 0;">
                            @else
                                {{ $realmName }}
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 16px; line-height: 1.5; color: {{ $foreground }};">
                            <p style="margin: 0 0 16px;">{{ $greeting ?? __('Hello!') }}</p>
                            @foreach ($introLines as $line)
                                <p style="margin: 0 0 16px;">{{ $line }}</p>
                            @endforeach
                            @isset($actionText)
                                <p style="margin: 24px 0; text-align: center;">
                                    <a href="{{ $actionUrl }}" style="display: inline-block; padding: 12px 24px; background-color: {{ $primary }}; color: {{ $primaryForeground }}; text-decoration: none;">{{ $actionText }}</a>
                                </p>
                            @endisset
                            @foreach ($outroLines as $line)
                                <p style="margin: 0 0 16px;">{{ $line }}</p>
                            @endforeach
                            <p style="margin: 24px 0 0;">{{ $realmName }}</p>
                            @isset($actionText)
                                <p style="margin: 24px 0 0; font-size: 13px; word-break: break-all;">
                                    {{ __("If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\ninto your web browser:", ['actionText' => $actionText]) }}
                                    <a href="{{ $actionUrl }}" style="color: {{ $foreground }};">{{ $displayableActionUrl }}</a>
                                </p>
                            @endisset
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
