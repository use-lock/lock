<?php
declare(strict_types=1);

namespace App\Realms\Support;

use App\Realms\Enums\SocialProviderDriver;
use Illuminate\Support\Facades\Validator;

final class SocialProviderConfiguration
{
    /**
     * @param  array<string, string|null>  $config
     * @return array<string, string>
     */
    public static function validate(SocialProviderDriver $driver, array $config, bool $partial = false): array
    {
        $rules = ['config' => ['array:'.implode(',', $driver->fields())]];

        foreach ($driver->fields() as $field) {
            $secret = in_array($field, $driver->secrets(), true);
            $rules['config.'.$field] = [
                ...($partial ? ['sometimes'] : []),
                $partial && $secret ? 'nullable' : 'required',
                'string',
                'max:'.($field === 'private_key' ? 8000 : ($secret ? 1000 : 255)),
            ];
        }

        Validator::make(['config' => $config], $rules)->validate();

        return array_map(fn (?string $value): string => $value ?? '', $config);
    }
}
