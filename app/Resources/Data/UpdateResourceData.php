<?php
declare(strict_types=1);

namespace App\Resources\Data;

use App\Resources\Support\ResourceIdentifier;
use App\Shared\Data\ApiData;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Optional;

final class UpdateResourceData extends ApiData
{
    /** @param array<int, ResourceScopeChangeData>|Optional $scopes */
    public function __construct(
        #[SpecProperty('A path relative to the realm issuer, or an absolute URI with a host and no fragment.')]
        #[Max(255), Rule(new ResourceIdentifier)]
        public Optional|string $identifier,
        #[SpecProperty('Human readable name of the protected resource.')]
        #[Max(255)]
        public Optional|string $name,
        #[SpecProperty('Scope changes keyed by value: new values create, existing values update, delete: true removes. Omitted entries remain unchanged; an empty array changes nothing.')]
        public Optional|array $scopes = new Optional,
    ) {}
}
