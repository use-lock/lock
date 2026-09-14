<?php
declare(strict_types=1);

namespace App\Clients\Http\Api\V1\Resources;

use App\Shared\Data\Data;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Carbon\CarbonImmutable;
use Lock\Server\Clients\Models\Client;
use Lock\Server\Shared\Clients\TokenEndpointAuthMethod;

/**
 * How a client reads on the wire. The version segment in the namespace is what
 * makes a breaking change to it a new folder rather than an edit to the shape
 * every client already reads.
 */
final class ClientData extends Data
{
    /**
     * @param  list<string>  $grantTypes
     * @param  list<string>  $redirectUris
     * @param  list<string>  $postLogoutRedirectUris
     * @param  list<string>  $scopes
     */
    public function __construct(
        #[SpecProperty('The identifier a relying party authenticates with, and its path in this API.')]
        public string $clientId,
        #[SpecProperty('The slug of the realm the client belongs to.')]
        public string $realm,
        #[SpecProperty('Human readable name, shown to the identity on the consent screen.')]
        public string $name,
        #[SpecProperty('How the client authenticates at the token endpoint.')]
        public TokenEndpointAuthMethod $tokenEndpointAuthMethod,
        #[SpecProperty('Whether the client holds a secret.')]
        public bool $confidential,
        #[SpecProperty('The grants the client may use.')]
        public array $grantTypes,
        #[SpecProperty('Where an authorization response may be sent back to.')]
        public array $redirectUris,
        #[SpecProperty('Where an end-session request may return to.')]
        public array $postLogoutRedirectUris,
        #[SpecProperty('Whether the identity is asked to consent before the client is granted its scopes.')]
        public bool $consentRequired,
        #[SpecProperty('Where a back-channel logout token is delivered.')]
        public ?string $backchannelLogoutUri,
        #[SpecProperty('The scopes the client is granted, from the realm’s client policy.')]
        public array $scopes,
        #[SpecProperty('Whether the client is blocked from obtaining tokens.')]
        public bool $revoked,
        #[SpecProperty('When the client was blocked, if it is.')]
        public ?CarbonImmutable $revokedAt,
        #[SpecProperty('When the client was created.')]
        public ?CarbonImmutable $createdAt,
        #[SpecProperty('When the client was last changed.')]
        public ?CarbonImmutable $updatedAt,
    ) {}

    public static function fromClient(Client $client): self
    {
        return new self(
            clientId: $client->client_id,
            realm: $client->realm,
            name: $client->name,
            tokenEndpointAuthMethod: $client->token_endpoint_auth_method,
            confidential: $client->snapshot()->confidential,
            grantTypes: array_values($client->grant_types),
            redirectUris: array_values($client->redirect_uris),
            postLogoutRedirectUris: array_values($client->post_logout_redirect_uris ?? []),
            consentRequired: $client->consent_required,
            backchannelLogoutUri: $client->backchannel_logout_uri,
            scopes: $client->snapshot()->assignedScopes(),
            revoked: $client->snapshot()->revoked,
            revokedAt: $client->revoked_at?->toImmutable(),
            createdAt: $client->created_at?->toImmutable(),
            updatedAt: $client->updated_at?->toImmutable(),
        );
    }
}
