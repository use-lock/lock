<?php

declare(strict_types=1);

use App\Admin\Enums\ManagementScope;
use Dedoc\Scramble\Generator;

it('documents resource operations and nested scope commands for the playground', function () {
    $document = app(Generator::class)();

    $collection = '/v1/realms/{realm}/resources';
    $item = $collection.'/{resource}';

    foreach ([$collection, $item] as $path) {
        expect($document['paths'][$path]['get']['security'] ?? null)
            ->toBe([['oauth2' => [ManagementScope::ResourcesRead->value]]]);
    }

    foreach (['post' => $collection, 'put' => $item, 'delete' => $item] as $method => $path) {
        expect($document['paths'][$path][$method]['security'] ?? null)
            ->toBe([['oauth2' => [ManagementScope::ResourcesWrite->value]]]);
    }

    expect($document['paths'][$collection]['post']['responses'] ?? [])
        ->toHaveKey('201')->not->toHaveKey('200');

    foreach (['CreateResourceData', 'CreateResourceScopeData', 'UpdateResourceData', 'ResourceScopeChangeData'] as $name) {
        expect($document['components']['schemas'][$name]['properties'] ?? [])->not->toBeEmpty();
    }

    expect($document['components']['schemas']['UpdateResourceData']['required'] ?? [])
        ->toBeEmpty()
        ->and($document['paths'])->not->toHaveKey('/v1/realms/{realm}/resources/{resource}/scopes')
        ->and($document['components']['schemas']['ResourceScopeChangeData']['properties'])->toHaveKeys(['value', 'description', 'delete'])->not->toHaveKey('id')
        ->and($document['components']['schemas']['ResourceScopeData']['properties'])->not->toHaveKey('id')
        ->and($document['components']['schemas']['ResourceData']['properties'])->toHaveKey('scopes')
        ->and($document['components']['schemas']['UpdateResourceData']['properties']['scopes']['items']['$ref'])->toBe('#/components/schemas/ResourceScopeChangeData');
});
