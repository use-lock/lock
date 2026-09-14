<?php
declare(strict_types=1);

namespace App\Resources\Actions;

use App\Admin\ManagementApi;
use App\Resources\Data\CreateResourceScopeData;
use App\Resources\Enums\ResourceAdminEvent;
use App\Resources\Models\Resource;
use App\Resources\Models\ResourceScope;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use LogicException;

final readonly class CreateResourceScope
{
    public function __construct(private ManagementApi $api) {}

    public function handle(Resource $resource, CreateResourceScopeData $data): ResourceScope
    {
        if ($this->api->owns($resource)) {
            throw new LogicException('The management API resource is reconciled by app:deploy and cannot be changed here.');
        }

        return DB::transaction(function () use ($resource, $data): ResourceScope {
            Validator::make(['value' => $data->value], [
                'value' => [Rule::unique(ResourceScope::class, 'value')->where('resource_id', $resource->id)],
            ])->validate();

            $scope = $resource->scopes()->create($data->toAttributes());

            Audit::record(ResourceAdminEvent::ScopeCreated, $scope, $resource->realm, [
                'realm' => $resource->realm->slug,
                'resource' => $resource->identifier,
                'value' => $scope->value,
            ]);

            return $scope;
        });
    }
}
