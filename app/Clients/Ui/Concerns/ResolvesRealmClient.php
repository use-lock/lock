<?php
declare(strict_types=1);

namespace App\Clients\Ui\Concerns;

use App\Realms\Models\Realm;
use Lock\Server\Clients\Models\Client;

/**
 * A client is only reachable through the realm it was created in: the
 * `client` context alone would let a signed reference from one realm's
 * console address another realm's client.
 */
trait ResolvesRealmClient
{
    private function realm(): Realm
    {
        return $this->contextModel('realm', Realm::class);
    }

    private function realmClient(): Client
    {
        $client = $this->realmClientOrNull();

        abort_unless($client instanceof Client, 404);

        return $client;
    }

    private function realmClientOrNull(): ?Client
    {
        $realm = $this->contextModelOrNull('realm', Realm::class);
        $client = $this->contextModelOrNull('client', Client::class);

        return $realm instanceof Realm && $client instanceof Client && $client->realm === $realm->slug ? $client : null;
    }
}
