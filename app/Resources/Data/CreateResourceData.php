<?php
declare(strict_types=1);

namespace App\Resources\Data;

use App\Resources\Support\ResourceIdentifier;
use App\Shared\Data\ApiData;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Rule;

final class CreateResourceData extends ApiData
{
    /** @param array<int, CreateResourceScopeData> $scopes */
    public function __construct(
        #[SpecProperty('A path relative to the realm issuer, or an absolute URI with a host and no fragment.')]
        #[Max(255), Rule(new ResourceIdentifier)]
        public string $identifier,
        #[SpecProperty('Human readable name of the protected resource.')]
        #[Max(255)]
        public string $name,
        #[SpecProperty('Scopes to create with this resource.')]
        public array $scopes = [],
    ) {}
}
