<?php
declare(strict_types=1);

namespace App\Resources\Actions;

use App\Resources\Data\CreateResourceScopeData;
use App\Resources\Data\ResourceScopeChangeData;
use App\Resources\Data\UpdateResourceScopeData;
use App\Resources\Models\Resource;
use App\Resources\Models\ResourceScope;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

final readonly class SyncResourceScopes
{
    public function __construct(
        private CreateResourceScope $createScope,
        private UpdateResourceScope $updateScope,
        private DeleteResourceScope $deleteScope,
    ) {}

    /** @param array<int, CreateResourceScopeData|ResourceScopeChangeData> $scopes */
    public function handle(Resource $resource, array $scopes): void
    {
        Validator::make(['scopes' => array_map(fn (CreateResourceScopeData|ResourceScopeChangeData $scope): array => ['value' => $scope->value], $scopes)], [
            'scopes.*.value' => ['distinct'],
        ])->validate();

        foreach ($scopes as $index => $data) {
            try {
                $this->apply($resource, $data);
            } catch (ValidationException $exception) {
                $errors = [];

                foreach ($exception->errors() as $field => $messages) {
                    $errors["scopes.{$index}.{$field}"] = $messages;
                }

                throw ValidationException::withMessages($errors);
            }
        }
    }

    private function apply(Resource $resource, CreateResourceScopeData|ResourceScopeChangeData $data): void
    {
        if ($data instanceof CreateResourceScopeData) {
            $this->createScope->handle($resource, $data);

            return;
        }

        $scope = $resource->scopes()->where('value', $data->value)->first();

        if ($data->delete) {
            if (! $scope instanceof ResourceScope) {
                throw ValidationException::withMessages(['value' => [__('validation.exists', ['attribute' => 'value'])]]);
            }

            $this->deleteScope->handle($scope);

            return;
        }

        if ($scope instanceof ResourceScope) {
            $this->updateScope->handle($scope, new UpdateResourceScopeData($data->value, $data->description));

            return;
        }

        $this->createScope->handle($resource, new CreateResourceScopeData(
            $data->value,
            $data->description instanceof Optional ? null : $data->description,
        ));
    }
}
