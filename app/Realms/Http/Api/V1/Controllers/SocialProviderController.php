<?php
declare(strict_types=1);

namespace App\Realms\Http\Api\V1\Controllers;

use App\Realms\Actions\CreateSocialProvider;
use App\Realms\Actions\DeleteSocialProvider;
use App\Realms\Actions\UpdateSocialProvider;
use App\Realms\Data\CreateSocialProviderData;
use App\Realms\Data\UpdateSocialProviderData;
use App\Realms\Http\Api\V1\Resources\SocialProviderData;
use App\Realms\Models\Realm;
use App\Realms\Models\RealmSocialProvider;
use Bambamboole\Spectacular\QueryBuilder;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response;
use Spatie\LaravelData\PaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final readonly class SocialProviderController
{
    public function __construct(
        private CreateSocialProvider $createProvider,
        private UpdateSocialProvider $updateProvider,
        private DeleteSocialProvider $deleteProvider,
    ) {}

    /** @return PaginatedDataCollection<array-key, SocialProviderData> */
    public function index(Realm $realm): PaginatedDataCollection
    {
        $providers = QueryBuilder::for($realm->socialProviders()->with('realm')->getQuery())
            ->allowedFilters(AllowedFilter::exact('key'), AllowedFilter::exact('driver'), AllowedFilter::exact('enabled'))
            ->allowedSorts('key', 'driver', 'created_at')
            ->defaultSort('key')
            ->apiPaginate();

        return SocialProviderData::collect($providers, PaginatedDataCollection::class);
    }

    public function show(Realm $realm, string $socialProvider): SocialProviderData
    {
        return SocialProviderData::fromProvider($this->provider($realm, $socialProvider));
    }

    #[IgnoreResponse(200)]
    #[Response(201, type: SocialProviderData::class)]
    public function store(CreateSocialProviderData $data, Realm $realm): SymfonyResponse
    {
        return SocialProviderData::fromProvider($this->createProvider->handle($realm, $data))
            ->toResponse(request())->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    public function update(UpdateSocialProviderData $data, Realm $realm, string $socialProvider): SocialProviderData
    {
        $provider = $this->provider($realm, $socialProvider);
        $this->updateProvider->handle($provider, $data);

        return SocialProviderData::fromProvider($provider->refresh());
    }

    public function destroy(Realm $realm, string $socialProvider): SymfonyResponse
    {
        $this->deleteProvider->handle($this->provider($realm, $socialProvider));

        return response()->noContent();
    }

    private function provider(Realm $realm, string $socialProvider): RealmSocialProvider
    {
        return $realm->socialProviders()->findOrFail($socialProvider);
    }
}
