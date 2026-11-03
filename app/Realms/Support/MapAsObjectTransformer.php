<?php

declare(strict_types=1);

namespace App\Realms\Support;

use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * PHP has one array type, so an empty map would reach the wire as `[]`, which a
 * typed client cannot read into a map.
 */
final class MapAsObjectTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): mixed
    {
        return $value === [] ? (object) [] : $value;
    }
}
