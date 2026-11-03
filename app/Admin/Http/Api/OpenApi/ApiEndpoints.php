<?php
declare(strict_types=1);

namespace App\Admin\Http\Api\OpenApi;

use Bambamboole\Spectacular\OpenApi\Endpoint;
use Bambamboole\Spectacular\OpenApi\EndpointDefinition;
use Illuminate\Routing\Router;

final readonly class ApiEndpoints implements EndpointDefinition
{
    public function __construct(private Router $router) {}

    public function endpoints(): array
    {
        $endpoints = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! str_starts_with($name, 'api.v1.')) {
                continue;
            }

            $category = in_array($name, [
                'api.v1.realms.index',
                'api.v1.realms.store',
                'api.v1.realms.show',
                'api.v1.realms.update',
                'api.v1.realms.destroy',
                'api.v1.realms.theme-assets.index',
                'api.v1.realms.theme-assets.show',
                'api.v1.realms.theme-assets.update',
                'api.v1.realms.theme-assets.destroy',
            ], true) ? 'Admin API' : 'Management API';

            // Raw bytes rather than a payload class, which is all Scramble can infer a body from.
            $body = $name === 'api.v1.realms.theme-assets.update' ? ['requestBody' => [
                'required' => true,
                'content' => ['application/octet-stream' => ['schema' => ['type' => 'string', 'format' => 'binary']]],
            ]] : [];

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $endpoints[] = Endpoint::route($name, $method, ['tags' => [$category], 'x-group' => $category === 'Admin API' ? 'admin' : 'management', ...$body]);
            }
        }

        return $endpoints;
    }

    public function schemas(): array
    {
        return [];
    }
}
