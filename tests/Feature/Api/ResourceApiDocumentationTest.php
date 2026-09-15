<?php

declare(strict_types=1);

use App\Admin\Enums\ManagementScope;
use Bambamboole\Spectacular\OpenApi\PublicOpenApiDocument;
use Dedoc\Scramble\Generator;

it('documents resource operations and nested scope commands for the playground', function () {
    $document = app(Generator::class)();

    $collection = '/v1/realms/{realm}/resources';
    $item = $collection.'/{resource}';

    foreach ($document['paths'] as $path => $operations) {
        if (! str_starts_with($path, '/v1/')) {
            continue;
        }

        $category = in_array($path, ['/v1/realms', '/v1/realms/{realm}'], true) ? 'Admin API' : 'Management API';

        foreach ($operations as $method => $operation) {
            if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                expect($operation['tags'])->toBe([$category]);
            }
        }
    }

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

it('publishes management and protocol operations while keeping realm administration internal', function () {
    $document = app(Generator::class)();
    $public = PublicOpenApiDocument::create($document) ?? throw new RuntimeException('The API must produce a public document.');

    expect($document['paths']['/v1/realms']['get']['security'])
        ->toBe([['adminOAuth2' => ['realms:read']]])
        ->and($document['paths']['/v1/realms']['get']['x-internal'])->toBeTrue()
        ->and($public['paths'] ?? [])->not->toHaveKeys(['/v1/realms', '/v1/realms/{realm}'])
        ->toHaveKeys(['/v1/realms/{realm}/clients', '/v1/realms/{realm}/resources', '/v1/realms/{realm}/social-providers', '/oauth/token', '/oauth/userinfo', '/.well-known/openid-configuration'])
        ->and($document['components']['securitySchemes']['adminOAuth2']['flows']['clientCredentials']['scopes'])
        ->toHaveKeys(['realms:read', 'realms:write'])->toHaveCount(2)
        ->and($document['components']['securitySchemes']['oauth2']['flows']['clientCredentials']['scopes'])
        ->not->toHaveKeys(['realms:read', 'realms:write'])
        ->toHaveKeys(['clients:read', 'resources:read', 'social-providers:read']);
});
