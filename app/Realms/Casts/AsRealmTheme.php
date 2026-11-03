<?php

declare(strict_types=1);

namespace App\Realms\Casts;

use App\Realms\Data\RealmTheme;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<RealmTheme, RealmTheme>
 */
final class AsRealmTheme implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): RealmTheme
    {
        $stored = is_string($value) ? json_decode($value, true) : $value;

        return RealmTheme::from(is_array($stored) ? $stored : []);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (! $value instanceof RealmTheme) {
            throw new InvalidArgumentException('A realm theme must be assigned as a '.RealmTheme::class.'.');
        }

        return (string) json_encode($value->toArray());
    }
}
