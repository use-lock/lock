<?php
declare(strict_types=1);

use App\Admin\Enums\ManagementScope;
use cebe\openapi\Reader;
use Dedoc\Scramble\Generator;
use Illuminate\Support\Facades\Route;

it('exports every OAuth and discovery route with its supported methods and correct server', function () {
    $document = app(Generator::class)();

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_starts_with($route->getName() ?? '', 'oidc.')) {
            continue;
        }

        $paths = str_contains($route->uri(), '{path?}')
            ? [str_replace('/{path?}', '', $route->uri()), str_replace('{path?}', '{path}', $route->uri())]
            : [$route->uri()];

        foreach ($paths as $path) {
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $operation = $document['paths']['/'.$path][strtolower($method)] ?? null;
                expect($operation)->not->toBeNull()
                    ->and($operation['tags'])->each->toBeIn(['Auth', 'User', 'Discovery'])
                    ->and($operation['servers'][0]['url'] ?? null)->toBe(config('app.url'));
            }
        }
    }

    $parsed = Reader::readFromJson(json_encode($document, JSON_THROW_ON_ERROR));
    expect($parsed->validate())->toBeTrue()
        ->and($parsed->getErrors())->toBeEmpty();
});

it('exports form grants and browser query parameters without inferred validation responses', function () {
    $document = app(Generator::class)();
    $token = $document['paths']['/oauth/token']['post'];
    $authorize = $document['paths']['/oauth/authorize'];

    expect($token['requestBody']['content'])->toHaveKey('application/x-www-form-urlencoded')
        ->and($token['responses'])->toHaveKeys(['200', '400', '401', '429', '500'])->not->toHaveKey('422')
        ->and($document['components']['schemas']['OAuthTokenRequest']['oneOf'])->toHaveCount(4)
        ->and($document['components']['schemas']['OAuthTokenResponse']['required'])->toBe(['access_token', 'token_type', 'expires_in'])
        ->and($document['components']['schemas']['OAuthTokenResponse']['properties'])->toHaveKeys(['refresh_token', 'id_token', 'issued_token_type']);

    $query = array_column($authorize['get']['parameters'], null, 'name');
    expect($query['code_challenge_method'])->toMatchArray(['in' => 'query', 'required' => true])
        ->and($query['redirect_uri'])->toMatchArray(['in' => 'query', 'required' => false])
        ->and($authorize['get'])->not->toHaveKey('requestBody')
        ->and($authorize['post']['requestBody']['content'])->toHaveKey('application/x-www-form-urlencoded')
        ->and($authorize['post']['responses'])->toHaveKeys(['200', '302', '400', '409', '419'])->not->toHaveKey('422');

    $protected = $document['paths']['/.well-known/oauth-protected-resource/{path}']['get'];
    expect($protected['parameters'][0])->toMatchArray(['in' => 'path', 'name' => 'path', 'required' => true])
        ->and($document['paths']['/.well-known/oauth-protected-resource']['get']['parameters'] ?? [])->toBeEmpty()
        ->and($document['paths']['/oauth/authorize/consent']['post']['responses'])->toHaveKeys(['302', '403', '419'])->not->toHaveKey('200');
});

it('documents client authentication and user bearer tokens without weakening management scopes', function () {
    $document = app(Generator::class)();

    expect($document['paths']['/oauth/userinfo']['get']['security'])->toBe([['protocolBearer' => []]])
        ->and($document['paths']['/oauth/userinfo']['post']['security'])->toBe([['protocolBearer' => []]])
        ->and($document['paths']['/oauth/userinfo']['get']['responses'])->toHaveKeys(['200', '401', '403'])
        ->and($document['paths']['/oauth/token']['post']['security'][0])->toBe(['clientSecretBasic' => []])
        ->and(json_encode($document['paths']['/oauth/token']['post']['security'][1], JSON_THROW_ON_ERROR))->toBe('{}')
        ->and($document['paths']['/.well-known/openid-configuration']['get']['security'])->toBe([])
        ->and($document['paths']['/oauth/register']['post']['security'])->toBe([])
        ->and($document['paths']['/oauth/authorize']['get']['security'])->toBe([])
        ->and($document['paths']['/v1/realms/{realm}/resources']['get']['security'])->toBe([['oauth2' => [ManagementScope::ResourcesRead->value]]]);
});
