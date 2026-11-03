<?php

declare(strict_types=1);

namespace App\Realms\Http\Controllers;

use App\Realms\Models\Realm;
use App\Realms\Models\RealmThemeAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Serves the realm the host names, so one realm's file does not exist on another
 * realm's host. The policy matters for SVG: an image can carry script, which an
 * `<img>` never runs but a browser opening the file directly on the realm's
 * origin would.
 */
final class RealmThemeAssetController
{
    public function __invoke(Request $request, string $name): Response
    {
        $asset = Realm::current()->themeAssets()->where('name', $name)->first();
        $contents = $asset === null ? null : Storage::disk(RealmThemeAsset::DISK)->get($asset->path);

        abort_if($asset === null || $contents === null, 404);

        return response($contents, 200, [
            'Content-Type' => $asset->mime_type,
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => $request->query('v') === $asset->version() ? 'public, max-age=31536000, immutable' : 'public, max-age=300',
            'ETag' => '"'.$asset->sha256.'"',
        ]);
    }
}
