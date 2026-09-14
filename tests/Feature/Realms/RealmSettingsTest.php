<?php

declare(strict_types=1);

namespace Tests\Feature\Realms;

use App\Realms\Data\RealmSettings;
use App\Realms\Data\RealmSettingsPatch;
use App\Realms\Support\RealmConfiguration;
use ReflectionClass;
use ReflectionParameter;

/**
 * @param  class-string  $data
 * @return list<string>
 */
function settingProperties(string $data): array
{
    return array_map(
        fn (ReflectionParameter $parameter): string => $parameter->getName(),
        new ReflectionClass($data)->getConstructor()?->getParameters() ?? [],
    );
}

it('accepts a submission for every setting the configuration declares', function () {
    expect(settingProperties(RealmSettingsPatch::class))
        ->toEqualCanonicalizing(settingProperties(RealmSettings::class))
        ->and(array_keys(RealmSettings::defaults()->toArray()))
        ->toEqualCanonicalizing(array_keys(RealmConfiguration::defaults()))
        ->and(array_keys(RealmConfiguration::defaults()))
        ->toEqualCanonicalizing(array_keys(RealmConfiguration::settings()));
});

it('carries a rule for every setting it declares', function () {
    $rules = RealmConfiguration::rules();

    foreach (array_keys(RealmConfiguration::defaults()) as $setting) {
        expect($rules)->toHaveKey($setting);
    }
});
