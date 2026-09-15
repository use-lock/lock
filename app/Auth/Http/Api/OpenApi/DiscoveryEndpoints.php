<?php
declare(strict_types=1);

namespace App\Auth\Http\Api\OpenApi;

use Bambamboole\Spectacular\OpenApi\Endpoint;
use Bambamboole\Spectacular\OpenApi\EndpointDefinition;

final class DiscoveryEndpoints implements EndpointDefinition
{
    public function endpoints(): array
    {
        $cache = ['Cache-Control' => ['schema' => ['type' => 'string'], 'description' => 'Publicly cacheable for 3600 seconds.']];
        $metadata = [
            'summary' => 'Discover provider capabilities',
            'description' => 'The request host selects the realm. Endpoint URLs, scopes and optional capabilities describe that realm. The registration endpoint is advertised only when dynamic registration is enabled.',
            'tags' => ['Discovery'],
            'security' => [],
            'requestBody' => null,
            'responses' => [200 => ProtocolDocumentation::response('OpenID Connect and OAuth authorization-server metadata.', 'OidcProviderMetadata') + ['headers' => $cache]],
        ];

        return [
            Endpoint::route('oidc.discovery', 'get', $metadata),
            Endpoint::route('oidc.authorization-server', 'get', $metadata + [
                'parameters' => [[
                    'in' => 'path', 'name' => 'path', 'required' => true,
                    'description' => 'Optional suffix accepted by the server. In domain-based realm routing the host selects the issuer and this suffix does not change the metadata.',
                    'schema' => ['type' => 'string'],
                ]],
            ]),
            Endpoint::route('oidc.jwks', 'get', [
                'summary' => 'Get public signing keys',
                'description' => 'Public RSA keys for validating tokens issued by this realm.',
                'tags' => ['Discovery'], 'security' => [], 'requestBody' => null,
                'responses' => [200 => ProtocolDocumentation::response('JSON Web Key Set.', 'OidcJwks') + ['headers' => $cache]],
            ]),
            Endpoint::route('oidc.protected-resource', 'get', [
                'summary' => 'Discover a protected resource',
                'description' => 'Discover a configured resource at the issuer root or a path relative to it. Unknown resources return 404.',
                'tags' => ['Discovery'], 'security' => [], 'requestBody' => null,
                'parameters' => [[
                    'in' => 'path', 'name' => 'path', 'required' => true,
                    'description' => 'Resource path relative to the issuer, for example api. Omit the suffix for a resource at the issuer root.',
                    'schema' => ['type' => 'string'],
                ]],
                'responses' => [
                    200 => ProtocolDocumentation::response('Protected-resource metadata.', 'OAuthProtectedResource') + ['headers' => $cache],
                    404 => ['description' => 'No resource is configured for this path.'],
                ],
            ]),
        ];
    }

    public function schemas(): array
    {
        $uri = ['type' => 'string', 'format' => 'uri'];
        $strings = ['type' => 'array', 'items' => ['type' => 'string']];
        $properties = [
            'issuer' => $uri,
            'authorization_endpoint' => $uri,
            'token_endpoint' => $uri,
            'jwks_uri' => $uri,
            'response_types_supported' => $strings,
            'response_modes_supported' => $strings,
            'grant_types_supported' => $strings,
            'subject_types_supported' => $strings,
            'id_token_signing_alg_values_supported' => $strings,
            'scopes_supported' => $strings,
            'claims_supported' => $strings,
            'acr_values_supported' => $strings,
            'claims_parameter_supported' => ['type' => 'boolean', 'enum' => [false]],
            'request_parameter_supported' => ['type' => 'boolean', 'enum' => [false]],
            'request_uri_parameter_supported' => ['type' => 'boolean', 'enum' => [false]],
            'code_challenge_methods_supported' => $strings,
            'authorization_response_iss_parameter_supported' => ['type' => 'boolean', 'enum' => [true]],
            'backchannel_logout_supported' => ['type' => 'boolean', 'enum' => [true]],
            'backchannel_logout_session_supported' => ['type' => 'boolean', 'enum' => [true]],
            'token_endpoint_auth_methods_supported' => $strings,
        ];

        return [
            'OidcProviderMetadata' => [
                'type' => 'object', 'required' => array_keys($properties),
                'properties' => $properties + [
                    'userinfo_endpoint' => $uri,
                    'end_session_endpoint' => $uri,
                    'introspection_endpoint' => $uri,
                    'introspection_endpoint_auth_methods_supported' => $strings,
                    'revocation_endpoint' => $uri,
                    'revocation_endpoint_auth_methods_supported' => $strings,
                    'registration_endpoint' => $uri,
                ],
            ],
            'OAuthProtectedResource' => [
                'type' => 'object',
                'required' => ['resource', 'authorization_servers', 'scopes_supported', 'bearer_methods_supported'],
                'properties' => [
                    'resource' => $uri,
                    'authorization_servers' => ['type' => 'array', 'items' => $uri],
                    'scopes_supported' => $strings,
                    'bearer_methods_supported' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['header']]],
                ],
            ],
            'OidcJwks' => [
                'type' => 'object', 'required' => ['keys'],
                'properties' => ['keys' => ['type' => 'array', 'items' => [
                    'type' => 'object', 'required' => ['kty', 'use', 'kid', 'alg', 'n', 'e'],
                    'properties' => [
                        'kty' => ['type' => 'string', 'enum' => ['RSA']],
                        'use' => ['type' => 'string', 'enum' => ['sig']],
                        'kid' => ['type' => 'string'],
                        'alg' => ['type' => 'string', 'enum' => ['RS256']],
                        'n' => ['type' => 'string', 'description' => 'Base64url-encoded RSA modulus.'],
                        'e' => ['type' => 'string', 'description' => 'Base64url-encoded RSA exponent.'],
                    ],
                ]]],
            ],
        ];
    }
}
