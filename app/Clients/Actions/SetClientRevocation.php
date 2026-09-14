<?php
declare(strict_types=1);

namespace App\Clients\Actions;

use App\Clients\Enums\ClientAdminEvent;
use App\Realms\Models\Realm;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Lock\Server\Clients\Models\Client;

final class SetClientRevocation
{
    public function handle(Client $client, bool $revoked): void
    {
        if ($client->snapshot()->revoked === $revoked) {
            return;
        }

        DB::transaction(function () use ($client, $revoked): void {
            $client->forceFill(['revoked_at' => $revoked ? now() : null])->save();

            Audit::record($revoked ? ClientAdminEvent::ClientRevoked : ClientAdminEvent::ClientRestored, $client, Realm::ofClient($client), [
                'realm' => $client->realm,
                'client_id' => $client->client_id,
            ]);
        });
    }
}
