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

final readonly class CreateClient
{
    public function __construct(private ClientRepository $clients) {}

    /**
     * The repository stamps the current realm and its default scopes on the
     * client, so it runs with the target realm current.
     */
    public function handle(Realm $realm, ClientAttributes $attributes): Client
    {
        return DB::transaction(fn (): Client => $realm->runAsCurrent(function () use ($realm, $attributes): Client {
            $client = $attributes->usesAuthorizationCode()
                ? $this->clients->createAuthorizationCodeGrantClient($attributes->name, $attributes->redirectUris, $attributes->confidential())
                : $this->clients->createClientCredentialsGrantClient($attributes->name);

            $client->forceFill($attributes->toArray())->save();

            Audit::record(ClientAdminEvent::ClientCreated, $client, $realm, [
                'realm' => $realm->slug,
                'client_id' => $client->client_id,
                'name' => $client->name,
            ]);

            return $client;
        }));
    }
}
