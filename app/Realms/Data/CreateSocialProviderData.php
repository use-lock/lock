<?php

declare(strict_types=1);

namespace App\Realms\Data;

use App\Realms\Enums\SocialProviderDriver;
use App\Shared\Data\ApiData;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;

final class CreateSocialProviderData extends ApiData
{
    public function __construct(
        #[SpecProperty('Immutable provider key used in callback URLs and linked accounts.')]
        #[Max(64), Regex('/^[a-z][a-z0-9-]*$/')]
        public string $key,
        #[SpecProperty('Upstream provider implementation.')]
        public SocialProviderDriver $driver,
        #[SpecProperty('Credentials required by the selected driver.')]
        public SocialProviderCredentialsData $config,
        #[SpecProperty('Whether the realm accepts sign-ins through this provider.')]
        public bool $enabled,
    ) {}
}
