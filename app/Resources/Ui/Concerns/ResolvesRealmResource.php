<?php
declare(strict_types=1);

namespace App\Resources\Ui\Concerns;

use App\Admin\ManagementApi;
use App\Realms\Models\Realm;
use App\Resources\Models\Resource;
use App\Resources\Models\ResourceScope;

/**
 * A resource is only reachable through its own realm, and a scope only through
 * its own resource: the `resource` or `resourceScope` context alone would let a
 * signed reference from one realm's console address another realm's rows.
 */
trait ResolvesRealmResource
{
    private function realm(): Realm
    {
        return $this->contextModel('realm', Realm::class);
    }

    private function resource(): Resource
    {
        $resource = $this->resourceOrNull();

        abort_unless($resource instanceof Resource, 404);

        return $resource;
    }

    private function resourceOrNull(): ?Resource
    {
        $realm = $this->contextModelOrNull('realm', Realm::class);
        $resource = $this->contextModelOrNull('resource', Resource::class);

        return $realm instanceof Realm && $resource instanceof Resource && $resource->belongsToRealm($realm) ? $resource : null;
    }

    /**
     * The management API's rows belong to `app:deploy`, so the console offers no
     * way to rename or delete them — the domain action refuses either way.
     */
    private function mutableResourceOrNull(): ?Resource
    {
        $resource = $this->resourceOrNull();

        return $resource instanceof Resource && ! app(ManagementApi::class)->owns($resource) ? $resource : null;
    }

    private function mutableResourceScopeOrNull(): ?ResourceScope
    {
        $scope = $this->resourceScopeOrNull();

        return $scope instanceof ResourceScope && $this->mutableResourceOrNull() instanceof Resource ? $scope : null;
    }

    private function resourceScope(): ResourceScope
    {
        $scope = $this->resourceScopeOrNull();

        abort_unless($scope instanceof ResourceScope, 404);

        return $scope;
    }

    private function resourceScopeOrNull(): ?ResourceScope
    {
        $resource = $this->resourceOrNull();
        $scope = $this->contextModelOrNull('resourceScope', ResourceScope::class);

        return $resource instanceof Resource && $scope instanceof ResourceScope && $scope->belongsToResource($resource) ? $scope : null;
    }
}
