<?php

declare(strict_types=1);

namespace App\Realms\Data;

use App\Shared\Data\ApiData;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Spatie\LaravelData\Optional;

final class UpdateSocialProviderData extends ApiData
{
    public function __construct(
        #[SpecProperty('Credentials to replace. Omitted values and blank secrets keep their stored value.')]
        public Optional|SocialProviderCredentialsData $config,
        #[SpecProperty('Whether the realm accepts sign-ins through this provider.')]
        public Optional|bool $enabled,
    ) {}
}
