<?php

declare(strict_types=1);

namespace App\Realms\Support;

use App\Realms\Models\Realm;
use Illuminate\Notifications\Messages\MailMessage;

final class RealmMailTheme
{
    /**
     * Mail clients do not reliably render SVG or WebP, so a logo in another
     * format gives way to the realm's name.
     */
    public static function apply(MailMessage $message, Realm $realm): MailMessage
    {
        $theme = $realm->theme;
        $logo = RealmThemeFiles::for($realm)->absoluteUrl($theme->value('mail-logo-url'));

        return $message
            ->from(
                $theme->value('mail-from-address') ?? (string) config('mail.from.address'),
                $theme->value('mail-from-name') ?? $realm->name,
            )
            ->view(['mail.realm-notification', 'mail.realm-notification-text'], [
                'realmName' => $realm->name,
                'logoUrl' => $logo !== null && preg_match('/\.(?:png|jpe?g)(?:\?|\z)/i', $logo) === 1 ? $logo : null,
                'background' => $theme->value('--lt-bg') ?? '#ffffff',
                'foreground' => $theme->value('--lt-fg') ?? '#1f2328',
                'primary' => $theme->value('--lt-primary') ?? '#0f766e',
                'primaryForeground' => $theme->value('--lt-primary-fg') ?? '#ffffff',
                'fontFamily' => $theme->value('--lt-font-sans') ?? 'system-ui, sans-serif',
            ]);
    }
}
