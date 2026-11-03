<?php

declare(strict_types=1);

namespace App\Realms\Ui\Concerns;

use App\Realms\Models\Realm;
use App\Realms\Models\RealmThemeAsset;

/**
 * A file is only reachable through its own realm: the `themeAsset` context alone
 * would let a signed reference from one realm's console address another realm's
 * rows.
 */
trait ResolvesRealmThemeAsset
{
    private function realm(): Realm
    {
        return $this->contextModel('realm', Realm::class);
    }

    private function themeAsset(): RealmThemeAsset
    {
        $asset = $this->themeAssetOrNull();

        abort_unless($asset instanceof RealmThemeAsset, 404);

        return $asset;
    }

    private function themeAssetOrNull(): ?RealmThemeAsset
    {
        $realm = $this->contextModelOrNull('realm', Realm::class);
        $asset = $this->contextModelOrNull('themeAsset', RealmThemeAsset::class);

        return $realm instanceof Realm && $asset instanceof RealmThemeAsset && $asset->belongsToRealm($realm) ? $asset : null;
    }
}
