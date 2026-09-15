<?php
declare(strict_types=1);

namespace App\Resources\Actions;

use App\Admin\ManagementApi;
use App\Resources\Data\UpdateResourceScopeData;
use App\Resources\Enums\ResourceAdminEvent;
use App\Resources\Models\ResourceScope;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use LogicException;

final readonly class UpdateResourceScope
{
    public function __construct(private ManagementApi $api) {}

    public function handle(ResourceScope $scope, UpdateResourceScopeData $data): void
    {
        $this->guardManagementApi($scope);

        DB::transaction(function () use ($scope, $data): void {
            $before = $scope->only(['value', 'description']);

            Validator::make($data->toAttributes(), [
                'value' => [Rule::unique(ResourceScope::class, 'value')->where('resource_id', $scope->resource_id)->ignore($scope)],
            ])->validate();

            $scope->fill($data->toAttributes())->save();

            $changes = [];

            foreach ($scope->only(['value', 'description']) as $key => $current) {
                if ($before[$key] !== $current) {
                    $changes[$key] = ['old' => $before[$key], 'new' => $current];
                }
            }

            if ($changes !== []) {
                Audit::record(ResourceAdminEvent::ScopeUpdated, $scope, $scope->resource->realm, [
                    'realm' => $scope->resource->realm->slug,
                    'resource' => $scope->resource->identifier,
                    'value' => $scope->value,
                    'changes' => $changes,
                ]);
            }
        });
    }

    private function guardManagementApi(ResourceScope $scope): void
    {
        if ($this->api->ownsScope($scope)) {
            throw new LogicException('Built-in API resources are reconciled by app:deploy and cannot be changed here.');
        }
    }
}
