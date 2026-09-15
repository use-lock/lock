<?php
declare(strict_types=1);

namespace App\Resources\Http\Api\V1\Controllers;

use App\Admin\ManagementApi;
use App\Realms\Models\Realm;
use App\Resources\Actions\CreateResource;
use App\Resources\Actions\DeleteResource;
use App\Resources\Actions\UpdateResource;
use App\Resources\Data\CreateResourceData;
use App\Resources\Data\UpdateResourceData;
use App\Resources\Http\Api\V1\Resources\ResourceData;
use App\Resources\Models\Resource;
use Bambamboole\Spectacular\QueryBuilder;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response;
use Spatie\LaravelData\PaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final readonly class ResourceController
{
    public function __construct(
        private CreateResource $createResource,
        private UpdateResource $updateResource,
        private DeleteResource $deleteResource,
        private ManagementApi $api,
    ) {}

    /**
     * List the resources of a realm
     *
     * @return PaginatedDataCollection<array-key, ResourceData>
     */
    public function index(Realm $realm): PaginatedDataCollection
    {
        $resources = QueryBuilder::for($realm->realmResources()->with(['realm', 'scopes'])->getQuery())
            ->allowedFilters(AllowedFilter::partial('name'), AllowedFilter::exact('identifier'))
            ->allowedSorts('name', 'identifier', 'created_at')
            ->defaultSort('name')
            ->apiPaginate();

        return ResourceData::collect($resources, PaginatedDataCollection::class);
    }

    /**
     * Show a protected resource
     */
    public function show(Realm $realm, string $resource): ResourceData
    {
        return ResourceData::fromResource($this->resource($realm, $resource));
    }

    /**
     * Create a protected resource
     */
    #[IgnoreResponse(200)]
    #[Response(201, type: ResourceData::class)]
    public function store(CreateResourceData $data, Realm $realm): SymfonyResponse
    {
        $resource = $this->createResource->handle($realm, $data);

        return ResourceData::fromResource($resource)->toResponse(request())->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Update a protected resource
     *
     * Fields left out retain their values. The built-in API resources are protected.
     */
    public function update(UpdateResourceData $data, Realm $realm, string $resource): ResourceData
    {
        $resource = $this->resource($realm, $resource);
        $this->guardManagementApi($resource);
        $this->updateResource->handle($resource, $data);

        return ResourceData::fromResource($resource->refresh());
    }

    /**
     * Delete a resource and its scopes
     *
     * The built-in API resources cannot be deleted.
     */
    public function destroy(Realm $realm, string $resource): SymfonyResponse
    {
        $resource = $this->resource($realm, $resource);
        $this->guardManagementApi($resource);
        $this->deleteResource->handle($resource);

        return response()->noContent();
    }

    private function resource(Realm $realm, string $resource): Resource
    {
        return $realm->realmResources()->findOrFail($resource);
    }

    private function guardManagementApi(Resource $resource): void
    {
        abort_if($this->api->owns($resource), SymfonyResponse::HTTP_FORBIDDEN, 'Built-in API resources cannot be changed.');
    }
}
