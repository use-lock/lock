<?php
declare(strict_types=1);

namespace App\Realms\Casts;

use App\Realms\Data\RealmSettings;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The column stores only what a realm moves away from the instance defaults, so
 * reading merges it back over them and writing strips them out again.
 *
 * @implements CastsAttributes<RealmSettings, RealmSettings>
 */
final class AsRealmSettings implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): RealmSettings
    {
        $stored = is_string($value) ? json_decode($value, true) : $value;

        return RealmSettings::defaults()->merge(is_array($stored) ? $stored : []);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (! $value instanceof RealmSettings) {
            throw new InvalidArgumentException('Realm settings must be assigned as a '.RealmSettings::class.'.');
        }

        return (string) json_encode($value->deviations());
    }
}
