<?php
declare(strict_types=1);

namespace App\Clients\Actions;

use App\Admin\Enums\ApiResource;
use App\Admin\Enums\BootstrapOutcome;
use App\Admin\ManagementApi;
use App\Clients\Enums\ClientAdminEvent;
use App\Realms\Models\Realm;
use App\Shared\Audit\Audit;
use App\Shared\Clients\Contracts\ProvisionsConsoleClient;
use Illuminate\Support\Facades\DB;
use Lock\Server\Clients\Models\Client;
use Lock\Server\Shared\Clients\ClientProvisioner;
use Lock\Server\Shared\Clients\FirstPartyClientProvisioningException;
use SensitiveParameter;

final readonly class ProvisionConsoleClient implements ProvisionsConsoleClient
{
    /** The key the package stamps on the client it provisions for self-SSO. */
    private const string ProvisioningKey = 'first-party';

    /**
     * The console login plus the machine grant infrastructure code
     * authenticates with, using the same credentials.
     */
    private const array GrantTypes = ['authorization_code', 'refresh_token', 'client_credentials'];

    public function __construct(
        private ClientProvisioner $provisioner,
        private ManagementApi $api,
    ) {}

    /**
     * @param  list<string>  $redirectUris
     * @param  list<string>  $postLogoutRedirectUris
     */
    public function handle(
        Realm $realm,
        string $name,
        string $clientId,
        #[SensitiveParameter] string $clientSecret,
        bool $trusted,
        array $redirectUris,
        array $postLogoutRedirectUris,
    ): BootstrapOutcome {
        return DB::transaction(fn (): BootstrapOutcome => $realm->runAsCurrent(function () use (
            $realm,
            $name,
            $clientId,
            $clientSecret,
            $trusted,
            $redirectUris,
            $postLogoutRedirectUris,
        ): BootstrapOutcome {
            $existing = $this->existingClient($realm, $clientId);
            $before = $existing instanceof Client ? $this->fingerprint($existing) : null;
            $audiences = $this->apiAudiences();

            $result = $this->provisioner->provision(
                name: $name,
                redirectUris: $redirectUris,
                postLogoutRedirectUris: $postLogoutRedirectUris,
                allowedExchangeAudiences: $audiences,
                adoptClientId: $existing?->client_id,
            );

            $client = Client::query()->inRealm()->findOrFail($result->client->key);
            $client->forceFill([
                'client_id' => $clientId,
                'grant_types' => self::GrantTypes,
                'optional_scopes' => $this->withApiScopes($client->optional_scopes ?? [], $audiences),
            ]);

            if (! hash_equals((string) $client->secret, $clientSecret)) {
                $client->secret = $clientSecret;
            }

            $client->save();

            $changed = $before === null || $before !== $this->fingerprint($client);
            $changed = $this->applySettings($realm, $clientId, $trusted) || $changed;

            return $this->record($client, $realm, $result->wasCreated, $changed);
        }));
    }

    /**
     * The console client addresses both of Lock's own APIs, with every scope
     * they own, so infrastructure code holding its credentials can drive them.
     *
     * @return list<string>
     */
    private function apiAudiences(): array
    {
        return array_map($this->api->audience(...), ApiResource::cases());
    }

    /**
     * @param  array<int, string>  $optionalScopes
     * @param  list<string>  $audiences
     * @return list<string>
     */
    private function withApiScopes(array $optionalScopes, array $audiences): array
    {
        $wildcards = array_map(fn (string $audience): string => $audience.' *', $audiences);

        return array_values(array_unique([...$optionalScopes, ...$wildcards]));
    }

    private function existingClient(Realm $realm, string $clientId): ?Client
    {
        $provisioned = Client::query()->inRealm()->where('provisioning_key', self::ProvisioningKey)->first();
        $named = Client::query()->inRealm()->where('client_id', $clientId)->first();

        if ($provisioned instanceof Client && $named instanceof Client && $provisioned->getKey() !== $named->getKey()) {
            throw new FirstPartyClientProvisioningException(
                "The client id [{$clientId}] already belongs to another client in realm [{$realm->slug}].",
            );
        }

        return $provisioned ?? $named;
    }

    /**
     * The secret is compared through its stored hash, which only changes when
     * the plain secret no longer verifies against it.
     *
     * @return array<string, mixed>
     */
    private function fingerprint(Client $client): array
    {
        return [
            'client_id' => $client->client_id,
            'name' => $client->name,
            'redirect_uris' => $client->redirect_uris,
            'post_logout_redirect_uris' => $client->post_logout_redirect_uris,
            'grant_types' => $client->grant_types,
            'allowed_exchange_audiences' => $client->allowed_exchange_audiences,
            'optional_scopes' => $client->optional_scopes,
            'provisioning_key' => $client->provisioning_key,
            'secret' => (string) $client->getRawOriginal('secret'),
        ];
    }

    private function applySettings(Realm $realm, string $clientId, bool $trusted): bool
    {
        $clients = $realm->clients();
        $changed = $clients->firstPartyClientId !== $clientId || $clients->firstPartyTrusted !== $trusted;

        $realm->first_party_client_id = $clientId;
        $realm->writeSettings(['first_party_trusted' => $trusted]);

        return $changed;
    }

    private function record(Client $client, Realm $realm, bool $created, bool $changed): BootstrapOutcome
    {
        $outcome = match (true) {
            $created => BootstrapOutcome::Created,
            $changed => BootstrapOutcome::Updated,
            default => BootstrapOutcome::Unchanged,
        };

        if ($outcome !== BootstrapOutcome::Unchanged) {
            Audit::record(
                $created ? ClientAdminEvent::ClientCreated : ClientAdminEvent::ClientUpdated,
                $client,
                $realm,
                [
                    'actor' => 'console:app:bootstrap',
                    'realm' => $realm->slug,
                    'client_id' => $client->client_id,
                    'name' => $client->name,
                ],
            );
        }

        return $outcome;
    }
}
