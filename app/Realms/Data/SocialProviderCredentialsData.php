<?php
declare(strict_types=1);

namespace App\Realms\Data;

use App\Shared\Data\ApiData;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Optional;

final class SocialProviderCredentialsData extends ApiData
{
    public function __construct(
        #[SpecProperty('Client ID required by every driver.')]
        #[Max(255)]
        public Optional|string $clientId = new Optional,
        #[SpecProperty('Write-only secret for Google, GitHub and OIDC. Required on creation; blank or null keeps the stored secret on update.')]
        #[Max(1000)]
        public Optional|string|null $clientSecret = new Optional,
        #[SpecProperty('Issuer URL required by the OIDC driver.')]
        #[Max(255)]
        public Optional|string $issuer = new Optional,
        #[SpecProperty('Team ID required by the Apple driver.')]
        #[Max(255)]
        public Optional|string $teamId = new Optional,
        #[SpecProperty('Key ID required by the Apple driver.')]
        #[Max(255)]
        public Optional|string $keyId = new Optional,
        #[SpecProperty('Write-only private key for Apple. Required on creation; blank or null keeps the stored key on update.')]
        #[Max(8000)]
        public Optional|string|null $privateKey = new Optional,
    ) {}

    /** @return array<string, string|null> */
    public function values(): array
    {
        return array_filter([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'issuer' => $this->issuer,
            'team_id' => $this->teamId,
            'key_id' => $this->keyId,
            'private_key' => $this->privateKey,
        ], fn (Optional|string|null $value): bool => ! $value instanceof Optional);
    }
}
