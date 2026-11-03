<?php

declare(strict_types=1);

namespace App\Realms\Http\Api\V1\Controllers;

use App\Realms\Actions\DeleteRealmThemeAsset;
use App\Realms\Actions\StoreRealmThemeAsset;
use App\Realms\Http\Api\V1\Resources\RealmThemeAssetData;
use App\Realms\Models\Realm;
use App\Realms\Models\RealmThemeAsset;
use Bambamboole\Spectacular\QueryBuilder;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final readonly class RealmThemeAssetController
{
    public function __construct(
        private StoreRealmThemeAsset $storeAsset,
        private DeleteRealmThemeAsset $deleteAsset,
    ) {}

    /**
     * List a realm's theme files
     *
     * @return PaginatedDataCollection<array-key, RealmThemeAssetData>
     */
    public function index(Realm $realm): PaginatedDataCollection
    {
        $assets = QueryBuilder::for($realm->themeAssets()->with('realm')->getQuery())
            ->allowedSorts('name', 'created_at')
            ->defaultSort('name')
            ->apiPaginate();

        return RealmThemeAssetData::collect($assets, PaginatedDataCollection::class);
    }

    /**
     * Show a theme file
     */
    public function show(Realm $realm, string $name): RealmThemeAssetData
    {
        return RealmThemeAssetData::fromAsset($this->asset($realm, $name));
    }

    /**
     * Upload a theme file
     *
     * The request body is the file itself. Its name decides its type: PNG, JPEG,
     * WebP, SVG or WOFF2, up to 5 MB. Uploading the same bytes again changes
     * nothing, and new bytes replace the file. A theme setting names it as
     * `asset:<name>`.
     *
     * @requestMediaType application/octet-stream
     */
    public function update(Request $request, Realm $realm, string $name): RealmThemeAssetData
    {
        return RealmThemeAssetData::fromAsset($this->storeAsset->handle($realm, $name, $request->getContent()));
    }

    /**
     * Delete a theme file
     *
     * A theme setting that still names the file is left as it is and renders
     * without it.
     */
    public function destroy(Realm $realm, string $name): SymfonyResponse
    {
        $this->deleteAsset->handle($this->asset($realm, $name));

        return response()->noContent();
    }

    private function asset(Realm $realm, string $name): RealmThemeAsset
    {
        return $realm->themeAssets()->where('name', $name)->firstOrFail();
    }
}
