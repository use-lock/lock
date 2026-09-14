<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Lattice\Support\Testing\ComponentNode;
use RuntimeException;

/**
 * @param  TestResponse<Response>  $response
 */
function latticeSchema(TestResponse $response, string $path = 'props.lattice.schema'): ComponentNode
{
    $schema = data_get(AssertableInertia::fromTestResponse($response)->toArray(), $path);

    if (! is_array($schema)) {
        throw new RuntimeException("The response renders no Lattice schema at [{$path}].");
    }

    return ComponentNode::root($schema);
}

/**
 * @param  TestResponse<Response>  $response
 * @return array<int, mixed>
 */
function renderedTabKeys(TestResponse $response): array
{
    return collect(latticeSchema($response)
        ->findAll(fn (ComponentNode $node): bool => $node->type() === 'tab'))
        ->map(fn (ComponentNode $node): mixed => $node->prop('value'))
        ->all();
}

/**
 * @return array<int, mixed>
 */
function menuItemHrefs(ComponentNode $menu): array
{
    return collect($menu->findAll(fn (ComponentNode $node): bool => $node->type() === 'menu-item'))
        ->map(fn (ComponentNode $node): mixed => $node->prop('href'))
        ->all();
}

/**
 * @param  TestResponse<Response>  $response
 */
function headerSlot(TestResponse $response): ?ComponentNode
{
    return latticeSchema($response)->find(fn (ComponentNode $node): bool => $node->key() === 'page-header-actions');
}
