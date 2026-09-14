<?php
declare(strict_types=1);

namespace App\Realms\Ui\Concerns;

use App\Realms\Models\Realm;
use App\Realms\Models\RealmSocialProvider;

/**
 * A provider is only reachable through its own realm: the `socialProvider`
 * context alone would let a signed reference from one realm's console address
 * another realm's rows.
 */
trait ResolvesRealmSocialProvider
{
    private function realm(): Realm
    {
        return $this->contextModel('realm', Realm::class);
    }

    private function socialProvider(): RealmSocialProvider
    {
        $provider = $this->socialProviderOrNull();

        abort_unless($provider instanceof RealmSocialProvider, 404);

        return $provider;
    }

    private function socialProviderOrNull(): ?RealmSocialProvider
    {
        $realm = $this->contextModelOrNull('realm', Realm::class);
        $provider = $this->contextModelOrNull('socialProvider', RealmSocialProvider::class);

        return $realm instanceof Realm && $provider instanceof RealmSocialProvider && $provider->belongsToRealm($realm) ? $provider : null;
    }
}
