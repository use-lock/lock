<?php

declare(strict_types=1);

namespace App\Realms\Data;

use App\Shared\Data\ApiData;
use Bambamboole\Spectacular\Attributes\SpecProperty;

final class RealmThemeFontFace extends ApiData
{
    public function __construct(
        #[SpecProperty('The font family this file belongs to, as the font stack settings name it.')]
        public string $family,
        #[SpecProperty('An `https` URL, or `asset:<name>` for a file uploaded to this realm.')]
        public string $file,
        #[SpecProperty('The weight the file holds, from 1 to 1000.')]
        public int $weight = 400,
        #[SpecProperty('The style the file holds: `normal` or `italic`.')]
        public string $style = 'normal',
    ) {}
}
