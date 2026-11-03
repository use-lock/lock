<?php

declare(strict_types=1);

namespace App\Realms\Support;

use Illuminate\Contracts\Support\Arrayable;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * laravel-data wraps a collection two levels below the root in its own `data`
 * key, which would make the same list read differently on the wire and in the
 * stored theme.
 */
final class PlainListTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): mixed
    {
        return array_map(fn (mixed $item): mixed => $item instanceof Arrayable ? $item->toArray() : $item, is_array($value) ? $value : []);
    }
}
