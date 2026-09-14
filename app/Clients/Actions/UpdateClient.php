<?php
declare(strict_types=1);

namespace App\Clients\Actions;

use App\Clients\Data\ClientAttributes;
use App\Clients\Enums\ClientAdminEvent;
use App\Realms\Models\Realm;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Lock\Server\Clients\ClientRepository;
use Lock\Server\Clients\Models\Client;

final readonly class UpdateClient
{
    public function __construct(private ClientRepository $clients) {}

    public function handle(Client $client, ClientAttributes $attributes): void
    {
        DB::transaction(function () use ($client, $attributes): void {
            $before = $client->only(array_keys($attributes->toArray()));
            $wasConfidential = $client->snapshot()->confidential;

            $client->forceFill($attributes->toArray());

            if ($wasConfidential && ! $attributes->confidential()) {
                $client->secret = null;
            }

            $client->save();

            if (! $wasConfidential && $attributes->confidential()) {
                $this->clients->regenerateSecret($client);
            }

            $changes = [];

            foreach ($client->only(array_keys($before)) as $key => $value) {
                if ($before[$key] != $value) {
                    $changes[$key] = ['old' => $before[$key], 'new' => $value];
                }
            }

            if ($changes !== []) {
                Audit::record(ClientAdminEvent::ClientUpdated, $client, Realm::ofClient($client), [
                    'realm' => $client->realm,
                    'client_id' => $client->client_id,
                    'changes' => $changes,
                ]);
            }
        });
    }
}
