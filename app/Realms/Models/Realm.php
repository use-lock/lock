<?php
declare(strict_types=1);

namespace App\Realms\Models;

use App\Auth\Models\User;
use App\Realms\Casts\AsRealmSettings;
use App\Realms\Data\RealmSettings;
use App\Realms\Enums\RealmDomainStatus;
use App\Resources\Models\Resource;
use App\Roles\Models\Role;
use App\Shared\Resources\Contracts\ProvidesRealmResources;
use Carbon\CarbonImmutable;
use Database\Factories\RealmFactory;
use Illuminate\Database\Eloquent\Attributes\Boot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lock\Server\Clients\Models\Client;
use Lock\Server\Shared\Realms\CurrentRealm;
use Lock\Server\Shared\Realms\Realm as RealmContract;
use Lock\Server\Shared\Realms\RealmResolver;
use Lock\Server\Shared\Realms\Settings\AuthenticationSettings;
use Lock\Server\Shared\Realms\Settings\BrokeringSettings;
use Lock\Server\Shared\Realms\Settings\ClientSettings;
use Lock\Server\Shared\Realms\Settings\CredentialSettings;
use Lock\Server\Shared\Realms\Settings\KeySettings;
use Lock\Server\Shared\Realms\Settings\LoginMethod;
use Lock\Server\Shared\Realms\Settings\LoginSettings;
use Lock\Server\Shared\Realms\Settings\MfaRequirement;
use Lock\Server\Shared\Realms\Settings\PasswordPolicy;
use Lock\Server\Shared\Realms\Settings\ResourceSettings;
use Lock\Server\Shared\Realms\Settings\ScopeSettings;
use Lock\Server\Shared\Realms\Settings\SessionSettings;
use Lock\Server\Shared\Realms\Settings\TokenSettings;
use LogicException;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $domain
 * @property RealmDomainStatus $domain_status
 * @property CarbonImmutable|null $domain_checked_at
 * @property string|null $domain_check_error
 * @property RealmSettings $settings
 * @property string|null $first_party_client_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 * @property-read Collection<int, RealmSocialProvider> $socialProviders
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, \App\Resources\Models\Resource> $realmResources
 * @property-read int|null $realm_resources_count
 *
 * @method static \Database\Factories\RealmFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Realm newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Realm newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Realm query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Realm whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Realm whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Realm whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Realm whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Realm whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'slug', 'domain', 'settings', 'first_party_client_id'])]
#[RouteKey('slug')]
final class Realm extends Model implements RealmContract
{
    /** @use HasFactory<RealmFactory> */
    use HasFactory, HasUuids;

    public const int NAME_MAX_LENGTH = 255;

    /** @var array<string, mixed> */
    protected $attributes = [
        'domain_status' => 'pending',
        'settings' => '{}',
    ];

    /**
     * The slug is what use-lock/server keys every row it stores by, so it is fixed
     * once the realm exists, and the master realm is what serves the console.
     */
    #[Boot]
    protected static function bootRealmInvariants(): void
    {
        self::updating(function (self $realm): void {
            if ($realm->isDirty('slug')) {
                throw new LogicException('Realm identifiers cannot be changed.');
            }
        });

        self::deleting(function (self $realm): void {
            if ($realm->isMaster()) {
                throw new LogicException('The administration realm cannot be deleted.');
            }
        });
    }

    public static function master(): self
    {
        return self::where('slug', config('lock.master_realm'))->firstOrFail();
    }

    public function isMaster(): bool
    {
        return $this->slug === config('lock.master_realm');
    }

    /**
     * The realm use-lock/server serves: the request host's, or the one
     * runAsCurrent(), a queued job or the console fallback names.
     */
    public static function current(): self
    {
        $realm = app(RealmResolver::class)->current();

        return $realm instanceof self ? $realm : throw new LogicException('The realm resolver returned a realm that is not a '.self::class.'.');
    }

    /**
     * The master realm is served from APP_URL's host, which is why its row
     * stores no domain.
     */
    public static function masterHost(): string
    {
        return strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    public static function ofClient(Client $client): self
    {
        return self::query()->where('slug', $client->realm)->sole();
    }

    public static function findByHost(string $host): ?self
    {
        $host = strtolower($host);
        $query = self::query();

        return $host === self::masterHost()
            ? $query->where('slug', config('lock.master_realm'))->first()
            : $query->where('domain', $host)->first();
    }

    public function host(): string
    {
        return $this->isMaster() ? self::masterHost() : (string) $this->domain;
    }

    /**
     * Every realm host shares APP_URL's scheme and port; a port only appears
     * locally, where `acme.localhost:8000` sits next to `localhost:8000`.
     */
    public function origin(): string
    {
        $appUrl = (string) config('app.url');
        $port = parse_url($appUrl, PHP_URL_PORT);

        return (parse_url($appUrl, PHP_URL_SCHEME) ?: 'https').'://'.$this->host().($port !== null ? ':'.$port : '');
    }

    public function runAsCurrent(callable $callback): mixed
    {
        return CurrentRealm::runAs($this->slug, $callback);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Role, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Not `resources()`: that name belongs to the package's realm contract,
     * which this model answers with the settings its configuration declares.
     *
     * @return HasMany<\App\Resources\Models\Resource, $this>
     */
    public function realmResources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'domain_status' => RealmDomainStatus::class,
            'domain_checked_at' => 'datetime',
            'settings' => AsRealmSettings::class,
        ];
    }

    public function identifier(): string
    {
        return $this->slug;
    }

    /** @return array<string, mixed> */
    public function configuration(): array
    {
        return $this->settings->toArray();
    }

    /**
     * @param  array<string, mixed>  $values  snake_case setting names
     */
    public function writeSettings(array $values): void
    {
        $this->settings = $this->settings->merge($values);
        $this->save();
    }

    public function tokens(): TokenSettings
    {
        return new TokenSettings(
            accessTokenLifetime: $this->settings->accessTokenLifetime,
            idTokenLifetime: $this->settings->idTokenLifetime,
            clientCredentialsLifetime: $this->settings->clientCredentialsLifetime,
            refreshTokenLifetime: $this->settings->refreshTokenLifetime,
        );
    }

    public function sessions(): SessionSettings
    {
        return new SessionSettings(
            absoluteLifetime: $this->settings->sessionAbsoluteLifetime,
            tokenTtl: $this->settings->sessionTokenTtl,
            tokenRefreshSkew: $this->settings->sessionTokenRefreshSkew,
        );
    }

    public function credentials(): CredentialSettings
    {
        return new CredentialSettings(
            challengeProviders: $this->settings->challengeProviders,
            totpSecretLength: $this->settings->totpSecretLength,
            totpWindow: $this->settings->totpWindow,
            recoveryCodes: $this->settings->recoveryCodes,
            password: $this->passwordPolicy(),
        );
    }

    public function passwordPolicy(): PasswordPolicy
    {
        $maxAgeDays = $this->settings->passwordMaxAgeDays;

        return new PasswordPolicy(
            minLength: $this->settings->passwordMinLength,
            mixedCase: $this->settings->passwordMixedCase,
            numbers: $this->settings->passwordNumbers,
            symbols: $this->settings->passwordSymbols,
            uncompromised: $this->settings->passwordUncompromised,
            history: $this->settings->passwordHistory,
            maxAgeDays: $maxAgeDays > 0 ? $maxAgeDays : null,
        );
    }

    public function clients(): ClientSettings
    {
        return new ClientSettings(
            dynamicRegistration: $this->settings->dynamicRegistration,
            allowedRedirectSchemes: $this->settings->allowedRedirectSchemes,
            allowedRedirectDomains: $this->settings->allowedRedirectDomains,
            defaultScopes: $this->settings->defaultScopes,
            optionalScopes: $this->settings->optionalScopes,
            tokenExchange: $this->settings->tokenExchange,
            firstPartyClientId: $this->first_party_client_id,
            firstPartyTrusted: $this->settings->firstPartyTrusted,
            trustedClients: $this->settings->trustedClients,
        );
    }

    /**
     * A realm user's only landing is their account page, and signing out of a
     * realm returns to that realm's own sign-in — never to the application
     * root, which is the master realm's relying-party entry.
     */
    public function login(): LoginSettings
    {
        return new LoginSettings(
            home: route('account', absolute: false),
            loginRoute: 'identity.login',
            logoutRedirect: route('identity.login', absolute: false),
        );
    }

    public function authentication(): AuthenticationSettings
    {
        return new AuthenticationSettings(
            methods: LoginMethod::listFromConfig($this->settings->loginMethods),
            mfa: MfaRequirement::fromConfig($this->settings->mfaRequirement),
            emailVerificationRequired: $this->settings->emailVerificationRequired,
        );
    }

    /** @return HasMany<RealmSocialProvider, $this> */
    public function socialProviders(): HasMany
    {
        return $this->hasMany(RealmSocialProvider::class);
    }

    public function brokering(): BrokeringSettings
    {
        return new BrokeringSettings(
            providers: $this->socialProviders()->where('enabled', true)->get()
                ->mapWithKeys(fn (RealmSocialProvider $provider): array => [$provider->key => $provider->brokeringConfig()])
                ->all(),
            linkByVerifiedEmail: $this->settings->linkByVerifiedEmail,
            autoProvision: $this->settings->autoProvision,
        );
    }

    public function resources(): ResourceSettings
    {
        return app(ProvidesRealmResources::class)->for($this);
    }

    public function scopes(): ScopeSettings
    {
        return ScopeSettings::fromConfig();
    }

    public function keys(): KeySettings
    {
        return KeySettings::fromConfig();
    }
}
