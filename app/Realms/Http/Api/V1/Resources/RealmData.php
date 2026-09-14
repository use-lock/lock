<?php
declare(strict_types=1);

namespace App\Realms\Http\Api\V1\Resources;

use App\Realms\Data\RealmSettings;
use App\Realms\Enums\RealmDomainStatus;
use App\Realms\Models\Realm;
use App\Shared\Data\Data;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Carbon\CarbonImmutable;

/**
 * How a realm reads on the wire. The version segment in the namespace is what
 * makes a breaking change to it a new folder rather than an edit to the shape
 * every client already reads.
 */
final class RealmData extends Data
{
    public function __construct(
        #[SpecProperty('Stable identifier of the realm, and its path in this API.')]
        public string $slug,
        #[SpecProperty('Human readable name shown to its users.')]
        public string $name,
        #[SpecProperty('The host the realm is served from.')]
        public string $host,
        #[SpecProperty('The realm’s OIDC issuer, the `iss` of every token it mints.')]
        public string $issuer,
        #[SpecProperty('The configured domain; null for the master realm, which follows APP_URL.')]
        public ?string $domain,
        #[SpecProperty('Whether the configured domain was seen to reach this instance.')]
        public RealmDomainStatus $domainStatus,
        #[SpecProperty('When the domain was last checked.')]
        public ?CarbonImmutable $domainCheckedAt,
        #[SpecProperty('Why the last domain check failed, when it did.')]
        public ?string $domainCheckError,
        #[SpecProperty('Whether this is the administration realm, which cannot be deleted or re-pointed.')]
        public bool $master,
        #[SpecProperty('The realm’s own policy.')]
        public RealmSettings $settings,
        #[SpecProperty('When the realm was created.')]
        public ?CarbonImmutable $createdAt,
        #[SpecProperty('When the realm was last changed.')]
        public ?CarbonImmutable $updatedAt,
    ) {}

    public static function fromRealm(Realm $realm): self
    {
        return new self(
            slug: $realm->slug,
            name: $realm->name,
            host: $realm->host(),
            issuer: $realm->origin(),
            domain: $realm->domain,
            domainStatus: $realm->domain_status,
            domainCheckedAt: $realm->domain_checked_at,
            domainCheckError: $realm->domain_check_error,
            master: $realm->isMaster(),
            settings: $realm->settings,
            createdAt: $realm->created_at,
            updatedAt: $realm->updated_at,
        );
    }
}
