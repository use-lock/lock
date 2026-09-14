<?php
declare(strict_types=1);

namespace App\Clients\Data;

use App\Clients\Support\AbsoluteUris;
use App\Clients\Support\ClientConfiguration;
use App\Clients\Support\ClientIsConsistent;
use App\Shared\Data\ApiData;
use Bambamboole\Spectacular\Attributes\SpecProperty;
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
 * What a client is created from. A create carries the whole client, so every
 * constraint on it is stated here — {@see ClientIsConsistent} covers the three
 * that span fields. The rule rides on `grant_types` as well because a payload
 * that leaves `redirect_uris` out never reaches that property's own rules.
 */
final class CreateClientData extends ApiData
{
    /**
     * @param  list<string>  $grantTypes
     * @param  list<string>  $redirectUris
     * @param  list<string>  $postLogoutRedirectUris
     */
    public function __construct(
        #[SpecProperty('Human readable name, shown to the identity on the consent screen.')]
        #[Max(255)]
        public string $name,
        #[SpecProperty('How the client authenticates at the token endpoint. `none` makes it a public client with no secret.')]
        public TokenEndpointAuthMethod $tokenEndpointAuthMethod,
        #[SpecProperty('The grants the client may use.')]
        #[ArrayType, ListType, Min(1), In(ClientAttributes::GRANT_TYPES), Rule(new ClientIsConsistent)]
        public array $grantTypes,
        #[SpecProperty('Where an authorization response may be sent back to. Required for the authorization code grant.')]
        #[ArrayType, ListType, Max(ClientConfiguration::URI_LIMIT), Rule(new AbsoluteUris, new ClientIsConsistent)]
        public Optional|array $redirectUris,
        #[SpecProperty('Where an end-session request may return to.')]
        #[ArrayType, ListType, Max(ClientConfiguration::URI_LIMIT), Rule(new AbsoluteUris)]
        public Optional|array $postLogoutRedirectUris,
        #[SpecProperty('Whether the identity is asked to consent before the client is granted its scopes.')]
        public Optional|bool $consentRequired,
        #[SpecProperty('Where a back-channel logout token is delivered.')]
        #[Max(2048), Url('http', 'https')]
        public Optional|string|null $backchannelLogoutUri,
    ) {}

    public function toClientAttributes(): ClientAttributes
    {
        return new ClientAttributes(
            name: $this->name,
            tokenEndpointAuthMethod: $this->tokenEndpointAuthMethod,
            redirectUris: $this->redirectUris instanceof Optional ? [] : $this->redirectUris,
            postLogoutRedirectUris: $this->postLogoutRedirectUris instanceof Optional ? [] : $this->postLogoutRedirectUris,
            grantTypes: $this->grantTypes,
            consentRequired: $this->consentRequired instanceof Optional ? true : $this->consentRequired,
            backchannelLogoutUri: $this->backchannelLogoutUri instanceof Optional ? null : $this->backchannelLogoutUri,
        );
    }
}
