<?php
declare(strict_types=1);

namespace App\Resources\Http\Api\V1\Resources;

use App\Resources\Models\ResourceScope;
use App\Shared\Data\Data;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Carbon\CarbonImmutable;

final class ResourceScopeData extends Data
{
    public function __construct(
        #[SpecProperty('Scope value, unique within its resource.')]
        public string $value,
        #[SpecProperty('Human readable description of the scope.')]
        public ?string $description,
        #[SpecProperty('When the scope was created.')]
        public ?CarbonImmutable $createdAt,
        #[SpecProperty('When the scope was last changed.')]
        public ?CarbonImmutable $updatedAt,
    ) {}

    public static function fromResourceScope(ResourceScope $scope): self
    {
        return new self(
            value: $scope->value,
            description: $scope->description,
            createdAt: $scope->created_at,
            updatedAt: $scope->updated_at,
        );
    }
}
