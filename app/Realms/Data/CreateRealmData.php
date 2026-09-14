<?php
declare(strict_types=1);

namespace App\Realms\Data;

use App\Realms\Models\Realm;
use App\Realms\Support\RealmDomain;
use App\Shared\Data\ApiData;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Spatie\LaravelData\Attributes\Validation\Lowercase;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Optional;

/**
 * What a realm is created from. The domain's uniqueness and the ban on the
 * master host are invariants of the realm rather than of this payload, so
 * {@see CreateRealm} checks them for every caller.
 */
final class CreateRealmData extends ApiData
{
    public function __construct(
        #[SpecProperty('Human readable name shown to the realm’s users.')]
        #[Max(Realm::NAME_MAX_LENGTH)]
        public string $name,
        #[SpecProperty('Stable identifier, lowercase and dash separated. It cannot be changed later.')]
        #[Max(63), Regex('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'), Unique('realms', 'slug')]
        public string $slug,
        #[SpecProperty('The domain the realm is served from. Lock checks that it reaches this instance, but keeps the realm either way.')]
        #[Max(253), Lowercase, Regex(RealmDomain::PATTERN)]
        public string $domain,
        #[SpecProperty('The realm’s policy. Settings left out follow the instance defaults.')]
        public Optional|RealmSettingsPatch $settings,
    ) {}
}
