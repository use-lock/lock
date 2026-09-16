<?php
declare(strict_types=1);

namespace App\Admin\Support;

use JsonException;
use RuntimeException;

/**
 * The OpenAPI documents `composer openapi:export` commits, one per `x-group`.
 * Production serves these files and never runs the generator. The export is
 * pinned to a placeholder host so the files are stable across environments;
 * reading swaps it for this instance's URL, which is what the reference's
 * playground executes against.
 */
final readonly class PublishedOpenApiDocuments
{
    /** The APP_URL `composer openapi:export` runs with. */
    public const string EXPORT_URL = 'https://lock.example';

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function read(string $group): array
    {
        $path = base_path("openapi.{$group}.json");
        $json = is_file($path) ? file_get_contents($path) : false;

        if ($json === false) {
            throw new RuntimeException("The OpenAPI document [{$path}] is missing. Run `composer openapi:export` and commit the result.");
        }

        $json = str_replace(self::EXPORT_URL, rtrim((string) config('app.url'), '/'), $json);

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
