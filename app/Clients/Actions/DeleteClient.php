<?php
declare(strict_types=1);

namespace App\Clients\Actions;

use App\Clients\Enums\ClientAdminEvent;
use App\Realms\Models\Realm;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Lock\Server\Clients\Models\Client;

final readonly class DeleteClient
{
    public function handle(Client $client): void
    {
        DB::transaction(function () use ($client): void {
            $client->delete();

            Audit::record(ClientAdminEvent::ClientDeleted, $client, Realm::ofClient($client), [
                'realm' => $client->realm,
                'client_id' => $client->client_id,
                'name' => $client->name,
            ]);
        });
    }
}
