<?php
declare(strict_types=1);

namespace App\Clients\Actions;

use App\Clients\Enums\ClientAdminEvent;
use App\Realms\Models\Realm;
use App\Shared\Audit\Audit;
use Lock\Server\Clients\Models\Client;

final class RevealClientSecret
{
    public function handle(Client $client): string
    {
        $secret = $client->secret;
        abort_unless($client->snapshot()->confidential && $secret !== null, 404);

        Audit::record(ClientAdminEvent::ClientSecretRevealed, $client, Realm::ofClient($client), [
            'client_id' => $client->client_id,
        ]);

        return $secret;
    }
}
