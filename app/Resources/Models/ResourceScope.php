<?php
declare(strict_types=1);

namespace App\Resources\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ResourceScopeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $resource_id
 * @property string $value
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read \App\Resources\Models\Resource $resource
 *
 * @method static \Database\Factories\ResourceScopeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceScope newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceScope newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceScope query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['resource_id', 'value', 'description'])]
final class ResourceScope extends Model
{
    /** @use HasFactory<ResourceScopeFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<\App\Resources\Models\Resource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function belongsToResource(Resource $resource): bool
    {
        return $this->resource_id === $resource->id;
    }

    public function label(): string
    {
        return $this->description ?? $this->value;
    }
}
