<?php
declare(strict_types=1);

namespace App\Roles\Actions;

use App\Resources\Models\ResourceScope;
use App\Roles\Enums\RoleAdminEvent;
use App\Roles\Models\Role;
use App\Roles\Services\RoleRules;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;

final readonly class UpdateRole
{
    public function __construct(private RoleRules $rules) {}

    /**
     * @param  list<string>  $scopeIds
     */
    public function handle(Role $role, string $name, ?string $description, array $scopeIds): void
    {
        $this->rules->ensureMutable($role);

        DB::transaction(function () use ($role, $name, $description, $scopeIds): void {
            $before = $role->only(['name', 'description']);
            $scopesBefore = $this->scopeSnapshot($role);

            $role->fill(['name' => $name, 'description' => $description])->save();
            $role->scopes()->sync($this->rules->scopeIds($role->realm, $scopeIds));

            $changes = [];

            foreach ($role->only(['name', 'description']) as $key => $value) {
                if ($before[$key] !== $value) {
                    $changes[$key] = ['old' => $before[$key], 'new' => $value];
                }
            }

            $scopesAfter = $this->scopeSnapshot($role);

            if (array_keys($scopesBefore) !== array_keys($scopesAfter)) {
                $changes['scopes'] = ['old' => array_values($scopesBefore), 'new' => array_values($scopesAfter)];
            }

            if ($changes !== []) {
                Audit::record(RoleAdminEvent::RoleUpdated, $role, $role->realm, [
                    'realm' => $role->realm->slug,
                    'name' => $role->name,
                    'changes' => $changes,
                ]);
            }
        });
    }

    /** @return array<string, array{resource: string, scope: string}> */
    private function scopeSnapshot(Role $role): array
    {
        return $role->load('scopes.resource')->scopes
            ->sortBy('id')
            ->mapWithKeys(fn (ResourceScope $scope): array => [$scope->id => [
                'resource' => $scope->resource->identifier,
                'scope' => $scope->value,
            ]])
            ->all();
    }
}
