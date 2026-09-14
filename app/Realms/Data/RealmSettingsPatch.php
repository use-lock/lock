<?php
declare(strict_types=1);

namespace App\Realms\Data;

use App\Realms\Support\RealmConfiguration;
use App\Shared\Data\ApiData;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Spatie\LaravelData\Optional;

/**
 * A submission against a realm's policy: every setting optional, so the same
 * shape serves a create — where the instance defaults stand in for what is left
 * out — and an update, where the realm's current values do.
 *
 * The constraints are not declared here. What a setting accepts is one thing,
 * and several settings only make sense against each other — a session token may
 * not outlive its session — so {@see RealmConfiguration::rules()} stays the one
 * place that says it and the actions validate the merged configuration against
 * it. This class says what the settings are called and what they mean.
 */
final class RealmSettingsPatch extends ApiData
{
    /**
     * @param  list<string>|Optional  $loginMethods
     * @param  list<string>|Optional  $challengeProviders
     * @param  list<string>|Optional  $allowedRedirectSchemes
     * @param  list<string>|Optional  $allowedRedirectDomains
     * @param  list<string>|Optional  $defaultScopes
     * @param  list<string>|Optional  $optionalScopes
     * @param  list<string>|Optional  $trustedClients
     */
    public function __construct(
        #[SpecProperty('Seconds an access token stays valid.')]
        public Optional|int $accessTokenLifetime,
        #[SpecProperty('Seconds an ID token stays valid.')]
        public Optional|int $idTokenLifetime,
        #[SpecProperty('Seconds a token issued to a machine client stays valid.')]
        public Optional|int $clientCredentialsLifetime,
        #[SpecProperty('Seconds a refresh token stays valid.')]
        public Optional|int $refreshTokenLifetime,
        #[SpecProperty('Seconds a sign-in session lives at most, however often it is refreshed.')]
        public Optional|int $sessionAbsoluteLifetime,
        #[SpecProperty('Seconds a session token stays valid before it is refreshed.')]
        public Optional|int $sessionTokenTtl,
        #[SpecProperty('Seconds before expiry at which a session token is refreshed early.')]
        public Optional|int $sessionTokenRefreshSkew,
        #[SpecProperty('The ways an identity may sign in: `password`, `passkey`, `social`.')]
        public Optional|array $loginMethods,
        #[SpecProperty('Whether an unverified address blocks sign-in.')]
        public Optional|bool $emailVerificationRequired,
        #[SpecProperty('Link an upstream identity to a local user with the same verified email address.')]
        public Optional|bool $linkByVerifiedEmail,
        #[SpecProperty('Create a realm user on their first verified upstream sign-in.')]
        public Optional|bool $autoProvision,
        #[SpecProperty('When a second factor is demanded: `never`, `if_enrolled`, `always`.')]
        public Optional|string $mfaRequirement,
        #[SpecProperty('The second factors on offer: `totp`, `webauthn`.')]
        public Optional|array $challengeProviders,
        #[SpecProperty('Bytes of a generated TOTP secret.')]
        public Optional|int $totpSecretLength,
        #[SpecProperty('Time steps either side of now a TOTP code is accepted in.')]
        public Optional|int $totpWindow,
        #[SpecProperty('How many recovery codes an identity is issued.')]
        public Optional|int $recoveryCodes,
        #[SpecProperty('Shortest password accepted.')]
        public Optional|int $passwordMinLength,
        #[SpecProperty('Whether a password must mix upper and lower case.')]
        public Optional|bool $passwordMixedCase,
        #[SpecProperty('Whether a password must carry a digit.')]
        public Optional|bool $passwordNumbers,
        #[SpecProperty('Whether a password must carry a symbol.')]
        public Optional|bool $passwordSymbols,
        #[SpecProperty('Whether a password is checked against known breaches.')]
        public Optional|bool $passwordUncompromised,
        #[SpecProperty('How many previous passwords may not be reused.')]
        public Optional|int $passwordHistory,
        #[SpecProperty('Days before a password must be changed; `0` never expires.')]
        public Optional|int $passwordMaxAgeDays,
        #[SpecProperty('Whether a client may register itself through RFC 7591.')]
        public Optional|bool $dynamicRegistration,
        #[SpecProperty('URI schemes a redirect URI may use.')]
        public Optional|array $allowedRedirectSchemes,
        #[SpecProperty('Hosts a redirect URI may point at; `*` allows any.')]
        public Optional|array $allowedRedirectDomains,
        #[SpecProperty('Scopes every client is granted without asking.')]
        public Optional|array $defaultScopes,
        #[SpecProperty('Scopes a client may request on top; `*` allows any of the catalog.')]
        public Optional|array $optionalScopes,
        #[SpecProperty('Whether the RFC 8693 token exchange grant is served.')]
        public Optional|bool $tokenExchange,
        #[SpecProperty('Whether the first-party client skips the consent screen.')]
        public Optional|bool $firstPartyTrusted,
        #[SpecProperty('Client ids that skip the consent screen.')]
        public Optional|array $trustedClients,
    ) {}

    /**
     * The settings the submission actually carried, under the names the
     * configuration uses.
     *
     * @return array<string, mixed>
     */
    public function submitted(): array
    {
        return $this->toArray();
    }
}
