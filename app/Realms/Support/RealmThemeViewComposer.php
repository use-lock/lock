<?php

declare(strict_types=1);

namespace App\Realms\Support;

use App\Realms\Models\Realm;
use Illuminate\View\View;

/**
 * Runs on the root view rather than in the middleware that shares the visitor's
 * appearance, so whatever path a page took there, the realm's fixed mode wins.
 */
final class RealmThemeViewComposer
{
    public function compose(View $view): void
    {
        $realm = Realm::current();

        if (! $realm->hasTheme()) {
            return;
        }

        $files = RealmThemeFiles::for($realm);
        $appearance = $realm->fixedAppearance();

        $view->with([
            'realmThemeStylesheet' => RealmThemeStylesheet::render($realm->theme, $files),
            'realmFavicon' => $files->url($realm->theme->value('favicon-url')),
            ...$appearance === null ? [] : ['appearance' => $appearance, 'forcedAppearance' => $appearance],
        ]);
    }
}
