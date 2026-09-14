<?php
declare(strict_types=1);

namespace App\Clients\Http\Api\V1\Resources;

use App\Shared\Data\Data;
use Bambamboole\Spectacular\Attributes\SpecProperty;

final class ClientSecretData extends Data
{
    public function __construct(
        #[SpecProperty('The identifier a relying party authenticates with.')]
        public string $clientId,
        #[SpecProperty('The credential disclosed by this explicit write-scoped operation; null for a public client.')]
        public ?string $secret,
    ) {}
}
