<?php
declare(strict_types=1);

namespace App\Resources\Support;

use App\Realms\Models\Realm;
use App\Resources\Models\Resource;
use App\Resources\Models\ResourceScope;
use App\Shared\Resources\Contracts\ProvidesRealmResources;
use Illuminate\Database\Eloquent\Collection;
use Lock\Server\Shared\Realms\Settings\ResourceSettings;

/**
 * Scoped rather than cached: use-lock/server builds a fresh realm model on every
 * RealmResolver::current() call, so one token request would re-read the
 * resource tables for each, while the rows themselves change at runtime.
 */
final class RealmResources implements ProvidesRealmResources
{
    /** @var array<string, Collection<int, \App\Resources\Models\Resource>> */
    private array $resources = [];

    public function for(Realm $realm): ResourceSettings
    {
        $resources = [];

        foreach ($this->resources($realm) as $resource) {
            $resources[$resource->identifier] = array_values(
                $resource->scopes->map(fn (ResourceScope $scope): string => $scope->value)->all(),
            );
        }

        return new ResourceSettings($resources);
    }

    /**
     * @return Collection<int, \App\Resources\Models\Resource>
     */
    public function resources(Realm $realm): Collection
    {
        return $this->resources[$realm->id] ??= $realm->realmResources()
            ->with('scopes')
            ->orderBy('identifier')
            ->get();
    }
}
