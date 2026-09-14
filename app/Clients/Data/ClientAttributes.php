<?php
declare(strict_types=1);

namespace App\Clients\Data;

use App\Clients\Support\ClientConfiguration;
use Lock\Server\Shared\Clients\TokenEndpointAuthMethod;

final readonly class ClientAttributes
{
    public const array GRANT_TYPES = ['authorization_code', 'refresh_token', 'client_credentials'];

    /** @var list<string> */
    public array $grantTypes;

    /** @var list<string> */
    public array $redirectUris;

    /** @var list<string> */
    public array $postLogoutRedirectUris;

    /**
     * @param  list<string>  $redirectUris
     * @param  list<string>  $postLogoutRedirectUris
     * @param  list<string>  $grantTypes
     */
    public function __construct(
        public string $name,
        public TokenEndpointAuthMethod $tokenEndpointAuthMethod,
        array $redirectUris,
        array $postLogoutRedirectUris,
        array $grantTypes,
        public bool $consentRequired = true,
        public ?string $backchannelLogoutUri = null,
    ) {
        ClientConfiguration::validate([
            'name' => $name,
            'token_endpoint_auth_method' => $tokenEndpointAuthMethod->value,
            'grant_types' => $grantTypes,
            'redirect_uris' => $redirectUris,
            'post_logout_redirect_uris' => $postLogoutRedirectUris,
            'consent_required' => $consentRequired,
            'backchannel_logout_uri' => $backchannelLogoutUri,
        ]);

        $this->grantTypes = array_values(array_unique($grantTypes));
        $this->redirectUris = self::uris($redirectUris);
        $this->postLogoutRedirectUris = self::uris($postLogoutRedirectUris);
    }

    /**
     * @param  list<string>  $uris
     * @return list<string>
     */
    private static function uris(array $uris): array
    {
        return array_values(array_unique(array_map(trim(...), $uris)));
    }

    public function confidential(): bool
    {
        return $this->tokenEndpointAuthMethod->requiresSecret();
    }

    public function usesAuthorizationCode(): bool
    {
        return in_array('authorization_code', $this->grantTypes, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'token_endpoint_auth_method' => $this->tokenEndpointAuthMethod,
            'redirect_uris' => $this->redirectUris,
            'post_logout_redirect_uris' => $this->postLogoutRedirectUris,
            'grant_types' => $this->grantTypes,
            'consent_required' => $this->consentRequired,
            'backchannel_logout_uri' => $this->backchannelLogoutUri,
        ];
    }
}
