<?php
declare(strict_types=1);

namespace App\Shared\Data;

use Illuminate\Support\Arr;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data as SpatieData;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Base for all Data objects: payloads and output map to snake_case, and
 * toAttributes() turns the provided (non-Optional) properties into a
 * Model::fill() payload.
 */
#[MapName(SnakeCaseMapper::class)]
abstract class Data extends SpatieData
{
    /**
     * @param  list<string>|null  $only  snake_case attribute names
     * @return array<string, mixed>
     */
    public function toAttributes(?array $only = null): array
    {
        $attributes = $this->toArray();

        return $only === null ? $attributes : Arr::only($attributes, $only);
    }
}
