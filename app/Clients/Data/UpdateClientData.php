<?php
declare(strict_types=1);

namespace App\Clients\Data;

use App\Clients\Support\AbsoluteUris;
use App\Clients\Support\ClientConfiguration;
use App\Shared\Data\ApiData;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Lock\Server\Clients\Models\Client;
use Lock\Server\Shared\Clients\TokenEndpointAuthMethod;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\ListType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Optional;

/**
 * What a client can be changed to. Everything left out keeps its current
 * value, so {@see self::toClientAttributes()} merges the submission onto the stored
 * client and the whole result is held to {@see ClientAttributes}.
 */
final class UpdateClientData extends ApiData
{
    /**
     * @param  list<string>|Optional  $grantTypes
     * @param  list<string>|Optional  $redirectUris
     * @param  list<string>|Optional  $postLogoutRedirectUris
     */
    public function __construct(
        #[SpecProperty('Human readable name, shown to the identity on the consent screen.')]
        #[Max(255)]
        public Optional|string $name,
        #[SpecProperty('How the client authenticates at the token endpoint. Turning a public client confidential mints a secret; the other way around drops it.')]
        public Optional|TokenEndpointAuthMethod $tokenEndpointAuthMethod,
        #[SpecProperty('The grants the client may use. Replaces the current set.')]
        #[ArrayType, ListType, Min(1), In(ClientAttributes::GRANT_TYPES)]
        public Optional|array $grantTypes,
        #[SpecProperty('Where an authorization response may be sent back to. Replaces the current list.')]
        #[ArrayType, ListType, Max(ClientConfiguration::URI_LIMIT), Rule(new AbsoluteUris)]
        public Optional|array $redirectUris,
        #[SpecProperty('Where an end-session request may return to. Replaces the current list.')]
        #[ArrayType, ListType, Max(ClientConfiguration::URI_LIMIT), Rule(new AbsoluteUris)]
        public Optional|array $postLogoutRedirectUris,
        #[SpecProperty('Whether the identity is asked to consent before the client is granted its scopes.')]
        public Optional|bool $consentRequired,
        #[SpecProperty('Where a back-channel logout token is delivered; `null` clears it.')]
        #[Max(2048), Url('http', 'https')]
        public Optional|string|null $backchannelLogoutUri,
    ) {}

    public function toClientAttributes(Client $client): ClientAttributes
    {
        return CreateClientData::from(ClientConfiguration::merge(
            $this->toAttributes(),
            $client->only(['name', 'token_endpoint_auth_method', 'grant_types', 'redirect_uris', 'post_logout_redirect_uris', 'consent_required', 'backchannel_logout_uri']),
        ))->toClientAttributes();
    }
}
