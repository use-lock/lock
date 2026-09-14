<?php
declare(strict_types=1);

namespace App\Resources\Support;

use App\Realms\Models\Realm;
use App\Resources\Models\Resource;
use Lock\Server\Shared\Realms\RealmAudiences;
use Lock\Server\Shared\Realms\RealmResolver;
use Lock\Server\Shared\Scopes\ScopeCatalog;

/**
 * Only the requested resource's scopes are served, which keeps the realm from
 * issuing a scope the addressed resource never declared.
 */
final readonly class ResourceScopeCatalog implements ScopeCatalog
{
    public function __construct(
        private RealmResolver $realms,
        private RealmAudiences $audiences,
        private RealmResources $resources,
    ) {}

    /**
     * @param  list<string>  $audiences
     * @return array<string, string>
     */
    public function scopes(array $audiences): array
    {
        $realm = $this->realms->current();

        if (! $realm instanceof Realm) {
            return [];
        }

        $catalog = [];

        foreach ($this->resources->resources($realm) as $resource) {
            if (! in_array($this->identifier($resource), $audiences, true)) {
                continue;
            }

            foreach ($resource->scopes as $scope) {
                $catalog[$scope->value] ??= $scope->label();
            }
        }

        return $catalog;
    }

    private function identifier(Resource $resource): string
    {
        return $resource->isAbsolute()
            ? $resource->identifier
            : $this->audiences->protectedResource($resource->identifier);
    }
}
