<?php
declare(strict_types=1);

namespace App\Realms\Http\Api\V1\Resources;

use App\Realms\Enums\SocialProviderDriver;
use App\Realms\Models\RealmSocialProvider;
use App\Shared\Data\Data;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Carbon\CarbonImmutable;

final class SocialProviderData extends Data
{
    public function __construct(
        #[SpecProperty('Provider UUID used in API paths.')]
        public string $id,
        #[SpecProperty('Realm slug.')]
        public string $realm,
        #[SpecProperty('Immutable provider key used for social login and linked accounts.')]
        public string $key,
        #[SpecProperty('Immutable upstream provider implementation.')]
        public SocialProviderDriver $driver,
        #[SpecProperty('Whether sign-ins through this provider are enabled.')]
        public bool $enabled,
        #[SpecProperty('Public configuration only. Client secrets and private keys are never returned.')]
        public SocialProviderPublicConfigData $config,
        #[SpecProperty('Callback URL to register with the upstream provider.')]
        public string $callbackUrl,
        #[SpecProperty('When the provider was created.')]
        public ?CarbonImmutable $createdAt,
        #[SpecProperty('When the provider was last changed.')]
        public ?CarbonImmutable $updatedAt,
    ) {}

    public static function fromProvider(RealmSocialProvider $provider): self
    {
        return new self(
            id: $provider->id,
            realm: $provider->realm->slug,
            key: $provider->key,
            driver: $provider->driver,
            enabled: $provider->enabled,
            config: SocialProviderPublicConfigData::from(array_intersect_key($provider->config, array_flip(array_diff($provider->driver->fields(), $provider->driver->secrets())))),
            callbackUrl: $provider->callbackUrl(),
            createdAt: $provider->created_at,
            updatedAt: $provider->updated_at,
        );
    }
}
