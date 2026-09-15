<?php
declare(strict_types=1);

namespace App\Auth\Http\Api\OpenApi;

final class ProtocolDocumentation
{
    /** @return array<string, string> */
    public static function reference(string $schema): array
    {
        return ['$ref' => "#/components/schemas/{$schema}"];
    }

    /** @return array<string, mixed> */
    public static function response(string $description, string $schema): array
    {
        return ['description' => $description, 'content' => ['application/json' => ['schema' => self::reference($schema)]]];
    }

    /** @return array<string, mixed> */
    public static function body(string $schema, string $contentType = 'application/x-www-form-urlencoded'): array
    {
        return ['required' => true, 'content' => [$contentType => ['schema' => self::reference($schema)]]];
    }

    /** @return array<int, array<string, mixed>|null> */
    public static function clientErrors(): array
    {
        return [
            400 => self::response('OAuth error: invalid_request, invalid_grant, invalid_scope, invalid_target, unauthorized_client or unsupported_grant_type.', 'OAuthError'),
            401 => self::response('Client authentication failed (invalid_client).', 'OAuthError') + [
                'headers' => ['WWW-Authenticate' => ['schema' => ['type' => 'string'], 'description' => 'Basic challenge for the current realm.']],
            ],
            422 => null,
            429 => ['description' => 'Rate limit exceeded.', 'headers' => ['Retry-After' => ['schema' => ['type' => 'integer']]]],
        ];
    }

    /** @return array<int, array<string, mixed>|null> */
    public static function browserResponses(): array
    {
        return [
            200 => ['description' => 'Browser interaction page (HTML, or an Inertia page when requested).', 'content' => [
                'text/html' => ['schema' => ['type' => 'string']],
                'application/json' => ['schema' => ['type' => 'object', 'additionalProperties' => true]],
            ]],
            302 => ['description' => 'Redirect to sign-in, a required action, the client callback or the post-logout destination.', 'headers' => [
                'Location' => ['schema' => ['type' => 'string', 'format' => 'uri-reference']],
            ]],
            400 => self::response('Invalid request before a trusted client redirect can be selected.', 'OAuthError'),
            409 => ['description' => 'External redirect for an Inertia request.', 'headers' => [
                'X-Inertia-Location' => ['schema' => ['type' => 'string', 'format' => 'uri-reference']],
            ]],
            422 => null,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  list<string>  $required
     * @return list<array<string, mixed>>
     */
    public static function queryParameters(array $properties, array $required = []): array
    {
        $parameters = [];
        foreach ($properties as $name => $schema) {
            $parameters[] = ['name' => $name, 'in' => 'query', 'required' => in_array($name, $required, true), 'schema' => $schema];
        }

        return $parameters;
    }
}
