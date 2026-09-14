<?php
declare(strict_types=1);

namespace App\Clients\Actions;

use App\Clients\Enums\ClientAdminEvent;
use App\Realms\Models\Realm;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Lock\Server\Clients\ClientRepository;
use Lock\Server\Clients\Models\Client;
use LogicException;

final readonly class RotateClientSecret
{
    public function __construct(private ClientRepository $clients) {}

    public function handle(Client $client): void
    {
        if (! $client->snapshot()->confidential) {
            throw new LogicException('A public client has no secret to rotate.');
        }

        DB::transaction(function () use ($client): void {
            $this->clients->regenerateSecret($client);

            Audit::record(ClientAdminEvent::ClientSecretRotated, $client, Realm::ofClient($client), [
                'realm' => $client->realm,
                'client_id' => $client->client_id,
            ]);
        });
    }
}
