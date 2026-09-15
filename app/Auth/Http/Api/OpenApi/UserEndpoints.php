<?php
declare(strict_types=1);

namespace App\Auth\Http\Api\OpenApi;

use Bambamboole\Spectacular\OpenApi\Endpoint;
use Bambamboole\Spectacular\OpenApi\EndpointDefinition;

final class UserEndpoints implements EndpointDefinition
{
    public function endpoints(): array
    {
        $operation = [
            'summary' => 'Read the authenticated user’s claims',
            'description' => 'Send a user access token in the Authorization: Bearer header. The token must include the openid scope. Service tokens are rejected. Additional claims depend on the granted scopes and the realm’s claim resolvers. Compare sub with the ID token’s subject.',
            'tags' => ['User'],
            'security' => [['protocolBearer' => []]],
            'requestBody' => null,
            'responses' => [
                200 => ProtocolDocumentation::response('The subject and the claims released to this client.', 'OidcUserinfo'),
                401 => ProtocolDocumentation::response('Missing or invalid user access token. When no token is supplied the response body is empty; otherwise it contains invalid_token.', 'OAuthError') + [
                    'headers' => ['WWW-Authenticate' => ['schema' => ['type' => 'string'], 'description' => 'Bearer challenge, with invalid_token when credentials were supplied.']],
                ],
                403 => ProtocolDocumentation::response('The access token does not grant openid (insufficient_scope).', 'OAuthError') + [
                    'headers' => ['WWW-Authenticate' => ['schema' => ['type' => 'string']]],
                ],
                422 => null,
            ],
        ];

        return [
            Endpoint::route('oidc.userinfo', 'get', $operation),
            Endpoint::route('oidc.userinfo', 'post', $operation),
        ];
    }

    public function schemas(): array
    {
        return [
            'OidcUserinfo' => [
                'type' => 'object',
                'required' => ['sub'],
                'properties' => [
                    'sub' => ['type' => 'string', 'description' => 'Stable subject identifier in this realm.'],
                    'name' => ['type' => 'string'],
                    'given_name' => ['type' => 'string'],
                    'family_name' => ['type' => 'string'],
                    'preferred_username' => ['type' => 'string'],
                    'picture' => ['type' => 'string', 'format' => 'uri'],
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'email_verified' => ['type' => 'boolean'],
                ],
                'additionalProperties' => true,
                'description' => 'Only sub is unconditional. Profile, email and custom claims depend on scopes and the configured resolvers.',
            ],
        ];
    }
}
