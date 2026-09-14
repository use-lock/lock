<?php
declare(strict_types=1);

namespace App\Clients\Http\Api\V1\Resources;

use App\Shared\Data\Data;
use Bambamboole\Spectacular\Attributes\SpecProperty;

final class CreatedClientData extends Data
{
    public function __construct(
        #[SpecProperty('The newly created client.')]
        public ClientData $client,
        #[SpecProperty('The credentials issued at creation. Regular client reads never contain them.')]
        public ClientSecretData $credentials,
    ) {}
}
