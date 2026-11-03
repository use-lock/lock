<?php

declare(strict_types=1);

namespace App\Realms\Http\Api\V1\Resources;

use App\Realms\Models\RealmThemeAsset;
use App\Shared\Data\Data;
use Bambamboole\Spectacular\Attributes\SpecProperty;

final class RealmThemeAssetData extends Data
{
    public function __construct(
        #[SpecProperty('The file\'s name. A theme setting refers to it as `asset:<name>`.')]
        public string $name,
        #[SpecProperty('The address the file is served from on the realm\'s host.')]
        public string $url,
        #[SpecProperty('The type the file is served with, taken from its name.')]
        public string $mimeType,
        #[SpecProperty('The file\'s size in bytes.')]
        public int $size,
        #[SpecProperty('The SHA-256 of the file\'s contents.')]
        public string $sha256,
    ) {}

    public static function fromAsset(RealmThemeAsset $asset): self
    {
        return new self(
            name: $asset->name,
            url: $asset->realm->origin().$asset->path(),
            mimeType: $asset->mime_type,
            size: $asset->size,
            sha256: $asset->sha256,
        );
    }
}
