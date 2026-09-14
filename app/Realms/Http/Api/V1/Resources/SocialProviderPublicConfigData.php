<?php
declare(strict_types=1);

namespace App\Realms\Http\Api\V1\Resources;

use App\Shared\Data\Data;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Spatie\LaravelData\Optional;

final class SocialProviderPublicConfigData extends Data
{
    public function __construct(
        #[SpecProperty('Upstream client ID.')]
        public string $clientId,
        #[SpecProperty('Issuer URL for OIDC providers.')]
        public Optional|string $issuer,
        #[SpecProperty('Team ID for Apple providers.')]
        public Optional|string $teamId,
        #[SpecProperty('Key ID for Apple providers.')]
        public Optional|string $keyId,
    ) {}
}
