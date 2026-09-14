<?php
declare(strict_types=1);

namespace App\Realms\Data;

use App\Shared\Data\Data;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Lock\Server\Shared\Realms\Settings\AuthenticationSettings;
use Lock\Server\Shared\Realms\Settings\BrokeringSettings;
use Lock\Server\Shared\Realms\Settings\ClientSettings;
use Lock\Server\Shared\Realms\Settings\CredentialSettings;
use Lock\Server\Shared\Realms\Settings\LoginMethod;
use Lock\Server\Shared\Realms\Settings\PasswordPolicy;
use Lock\Server\Shared\Realms\Settings\SessionSettings;
use Lock\Server\Shared\Realms\Settings\TokenSettings;

/**
 * A realm's whole policy, typed. The realm casts its `settings` column to this,
 * so every reader gets the value in the type the setting holds instead of
 * whatever JSON happened to carry, and the same class describes the settings in
 * the generated API document.
 */
final class RealmSettings extends Data
{
    /**
     * @param  list<string>  $loginMethods
     * @param  list<string>  $challengeProviders
     * @param  list<string>  $allowedRedirectSchemes
     * @param  list<string>  $allowedRedirectDomains
     * @param  list<string>  $defaultScopes
     * @param  list<string>  $optionalScopes
     * @param  list<string>  $trustedClients
     */
    public function __construct(
        #[SpecProperty('Seconds an access token stays valid.')]
        public int $accessTokenLifetime,
        #[SpecProperty('Seconds an ID token stays valid.')]
        public int $idTokenLifetime,
        #[SpecProperty('Seconds a token issued to a machine client stays valid.')]
        public int $clientCredentialsLifetime,
        #[SpecProperty('Seconds a refresh token stays valid.')]
        public int $refreshTokenLifetime,
        #[SpecProperty('Seconds a sign-in session lives at most, however often it is refreshed.')]
        public int $sessionAbsoluteLifetime,
        #[SpecProperty('Seconds a session token stays valid before it is refreshed.')]
        public int $sessionTokenTtl,
        #[SpecProperty('Seconds before expiry at which a session token is refreshed early.')]
        public int $sessionTokenRefreshSkew,
        #[SpecProperty('The ways an identity may sign in: `password`, `passkey`, `social`.')]
        public array $loginMethods,
        #[SpecProperty('Whether an unverified address blocks sign-in.')]
        public bool $emailVerificationRequired,
        #[SpecProperty('Link an upstream identity to a local user with the same verified email address.')]
        public bool $linkByVerifiedEmail,
        #[SpecProperty('Create a realm user on their first verified upstream sign-in.')]
        public bool $autoProvision,
        #[SpecProperty('When a second factor is demanded: `never`, `if_enrolled`, `always`.')]
        public string $mfaRequirement,
        #[SpecProperty('The second factors on offer: `totp`, `webauthn`.')]
        public array $challengeProviders,
        #[SpecProperty('Bytes of a generated TOTP secret.')]
        public int $totpSecretLength,
        #[SpecProperty('Time steps either side of now a TOTP code is accepted in.')]
        public int $totpWindow,
        #[SpecProperty('How many recovery codes an identity is issued.')]
        public int $recoveryCodes,
        #[SpecProperty('Shortest password accepted.')]
        public int $passwordMinLength,
        #[SpecProperty('Whether a password must mix upper and lower case.')]
        public bool $passwordMixedCase,
        #[SpecProperty('Whether a password must carry a digit.')]
        public bool $passwordNumbers,
        #[SpecProperty('Whether a password must carry a symbol.')]
        public bool $passwordSymbols,
        #[SpecProperty('Whether a password is checked against known breaches.')]
        public bool $passwordUncompromised,
        #[SpecProperty('How many previous passwords may not be reused.')]
        public int $passwordHistory,
        #[SpecProperty('Days before a password must be changed; `0` never expires.')]
        public int $passwordMaxAgeDays,
        #[SpecProperty('Whether a client may register itself through RFC 7591.')]
        public bool $dynamicRegistration,
        #[SpecProperty('URI schemes a redirect URI may use.')]
        public array $allowedRedirectSchemes,
        #[SpecProperty('Hosts a redirect URI may point at; `*` allows any.')]
        public array $allowedRedirectDomains,
        #[SpecProperty('Scopes every client is granted without asking.')]
        public array $defaultScopes,
        #[SpecProperty('Scopes a client may request on top; `*` allows any of the catalog.')]
        public array $optionalScopes,
        #[SpecProperty('Whether the RFC 8693 token exchange grant is served.')]
        public bool $tokenExchange,
        #[SpecProperty('Whether the first-party client skips the consent screen.')]
        public bool $firstPartyTrusted,
        #[SpecProperty('Client ids that skip the consent screen.')]
        public array $trustedClients,
    ) {}

    /**
     * The instance defaults every realm starts from, read from the package's own
     * configuration objects — which is why they cannot be property defaults.
     */
    public static function defaults(): self
    {
        $tokens = new TokenSettings;
        $sessions = new SessionSettings;
        $authentication = AuthenticationSettings::fromConfig();
        $credentials = new CredentialSettings;
        $clients = new ClientSettings;
        $password = PasswordPolicy::fromConfig();
        $brokering = BrokeringSettings::fromConfig();

        return new self(
            accessTokenLifetime: $tokens->accessTokenLifetime,
            idTokenLifetime: $tokens->idTokenLifetime,
            clientCredentialsLifetime: $tokens->clientCredentialsLifetime,
            refreshTokenLifetime: $tokens->refreshTokenLifetime,
            sessionAbsoluteLifetime: $sessions->absoluteLifetime,
            sessionTokenTtl: $sessions->tokenTtl,
            sessionTokenRefreshSkew: $sessions->tokenRefreshSkew,
            loginMethods: array_map(fn (LoginMethod $method): string => $method->value, $authentication->methods),
            emailVerificationRequired: $authentication->emailVerificationRequired,
            linkByVerifiedEmail: $brokering->linkByVerifiedEmail,
            autoProvision: $brokering->autoProvision,
            mfaRequirement: $authentication->mfa->value,
            challengeProviders: $credentials->challengeProviders,
            totpSecretLength: $credentials->totpSecretLength,
            totpWindow: $credentials->totpWindow,
            recoveryCodes: $credentials->recoveryCodes,
            passwordMinLength: $password->minLength,
            passwordMixedCase: $password->mixedCase,
            passwordNumbers: $password->numbers,
            passwordSymbols: $password->symbols,
            passwordUncompromised: $password->uncompromised,
            passwordHistory: $password->history,
            passwordMaxAgeDays: $password->maxAgeDays ?? 0,
            dynamicRegistration: $clients->dynamicRegistration,
            allowedRedirectSchemes: $clients->allowedRedirectSchemes,
            allowedRedirectDomains: $clients->allowedRedirectDomains,
            defaultScopes: $clients->defaultScopes,
            optionalScopes: $clients->optionalScopes,
            tokenExchange: $clients->tokenExchange,
            firstPartyTrusted: $clients->firstPartyTrusted,
            trustedClients: $clients->trustedClients,
        );
    }

    /**
     * @param  array<string, mixed>  $values  snake_case setting names
     */
    public function merge(array $values): self
    {
        return self::from([...$this->toArray(), ...$values]);
    }

    /**
     * What this realm moves away from the instance defaults — the only thing the
     * column stores, so a setting nobody changed keeps following its default.
     *
     * @return array<string, mixed>
     */
    public function deviations(): array
    {
        $defaults = self::defaults()->toArray();

        return array_filter(
            $this->toArray(),
            fn (mixed $value, string $key): bool => ! array_key_exists($key, $defaults) || $defaults[$key] !== $value,
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
