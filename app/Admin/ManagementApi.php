<?php
declare(strict_types=1);

namespace App\Admin;

use App\Admin\Enums\ApiResource;
use App\Admin\Enums\BootstrapOutcome;
use App\Admin\Enums\ManagementScope;
use App\Realms\Models\Realm;
use App\Resources\Enums\ResourceAdminEvent;
use App\Resources\Models\Resource;
use App\Resources\Models\ResourceScope;
use App\Roles\Enums\RoleAdminEvent;
use App\Roles\Models\Role;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Lock\Server\Shared\Realms\RealmAudiences;

/**
 * Deployment-owned OAuth protected resources and scope rows in the master realm.
 */
final readonly class ManagementApi
{
    /**
     * Relative to the issuer, so the audience follows APP_URL and RFC 9728
     * metadata is published under `.well-known/oauth-protected-resource/api`.
     */
    public const string RESOURCE = 'api';

    public const string NAME = 'Management API';

    public function __construct(private RealmAudiences $audiences) {}

    /**
     * The `aud` value a token for this API carries. Only resolvable while the
     * master realm is current, which every request to `api/*` is.
     */
    public function audience(ApiResource $resource = ApiResource::Management): string
    {
        return $this->audiences->protectedResource($resource->value);
    }

    /** The resource row and its scopes, as the deploy leaves them. */
    public function reconcile(Realm $realm): BootstrapOutcome
    {
        return DB::transaction(function () use ($realm): BootstrapOutcome {
            $created = false;
            $changed = false;

            foreach (ApiResource::cases() as $api) {
                $resource = $realm->realmResources()->firstOrNew(['identifier' => $api->value]);
                $isNew = ! $resource->exists;
                $resource->name = $api->label();
                $changed = $resource->isDirty() || $changed;
                $resource->save();
                $created = $created || $isNew;

                if ($isNew) {
                    Audit::record(ResourceAdminEvent::ResourceCreated, $resource, $realm, $this->context($realm, $api->value));
                }

                $changed = $this->reconcileScopes($resource, $realm, $api) || $changed;
            }

            return match (true) {
                $created => BootstrapOutcome::Created,
                $changed => BootstrapOutcome::Updated,
                default => BootstrapOutcome::Unchanged,
            };
        });
    }

    /**
     * The protected roles, as the deploy leaves them. Runs after
     * {@see self::reconcile()}: a role points at scope rows, so those have to
     * exist first.
     */
    public function reconcileRoles(Realm $realm): BootstrapOutcome
    {
        return DB::transaction(function () use ($realm): BootstrapOutcome {
            $scopes = $this->scopeRows($realm);
            $created = false;
            $changed = false;

            foreach (ManagementRoles::grants() as $name => $granted) {
                $role = $realm->roles()->firstOrNew(['name' => $name]);
                $isNew = ! $role->exists;
                $role->save();

                $wanted = array_map(fn (ManagementScope $scope): string => $scopes[$scope->value], $granted);

                $synced = $role->scopes()->sync($wanted);
                $touched = $synced['attached'] !== [] || $synced['detached'] !== [];

                if ($isNew || $touched) {
                    Audit::record(
                        $isNew ? RoleAdminEvent::RoleCreated : RoleAdminEvent::RoleUpdated,
                        $role,
                        $realm,
                        [...$this->context($realm), 'role' => $name],
                    );
                }

                $created = $created || $isNew;
                $changed = $changed || $touched;
            }

            return match (true) {
                $created => BootstrapOutcome::Created,
                $changed => BootstrapOutcome::Updated,
                default => BootstrapOutcome::Unchanged,
            };
        });
    }

    /** `app:deploy` owns these rows, so nothing else may rename or delete them. */
    public function owns(Resource $resource): bool
    {
        return ApiResource::tryFrom($resource->identifier) !== null && $resource->realm->isMaster();
    }

    public function ownsScope(ResourceScope $scope): bool
    {
        return $this->owns($scope->resource);
    }

    public function ownsRole(Role $role): bool
    {
        return $role->realm->isMaster() && in_array($role->name, ManagementRoles::names(), true);
    }

    /**
     * @return array<string, string> scope value to its row id
     */
    private function scopeRows(Realm $realm): array
    {
        return $realm->realmResources()
            ->whereIn('identifier', array_column(ApiResource::cases(), 'value'))
            ->with('scopes')
            ->get()
            ->flatMap(fn (Resource $resource): array => $resource->scopes->all())
            ->pluck('id', 'value')
            ->all();
    }

    private function reconcileScopes(Resource $resource, Realm $realm, ApiResource $api): bool
    {
        $changed = false;

        foreach ($api->scopes() as $scope) {
            $row = $resource->scopes()->firstOrNew(['value' => $scope->value]);
            $created = ! $row->exists;

            $row->description = $scope->description();

            if ($created || $row->isDirty()) {
                $row->save();
                $changed = true;

                Audit::record(
                    $created ? ResourceAdminEvent::ScopeCreated : ResourceAdminEvent::ScopeUpdated,
                    $row,
                    $realm,
                    [...$this->context($realm, $resource->identifier), 'value' => $row->value],
                );
            }
        }

        $removed = $resource->scopes()->whereNotIn('value', $api->values())->get();

        foreach ($removed as $row) {
            $row->delete();

            Audit::record(ResourceAdminEvent::ScopeDeleted, $row, $realm, [...$this->context($realm, $resource->identifier), 'value' => $row->value]);
        }

        $resource->unsetRelation('scopes');

        return $changed || $removed->isNotEmpty();
    }

    /** @return array<string, string> */
    private function context(Realm $realm, string $resource = self::RESOURCE): array
    {
        return [
            'actor' => 'console:app:bootstrap',
            'realm' => $realm->slug,
            'resource' => $resource,
        ];
    }
}
