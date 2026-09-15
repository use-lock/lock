<?php
declare(strict_types=1);

namespace App\Auth\Http\Api\OpenApi;

use Bambamboole\Spectacular\OpenApi\Endpoint;
use Bambamboole\Spectacular\OpenApi\EndpointDefinition;
use stdClass;

final class AuthEndpoints implements EndpointDefinition
{
    public function endpoints(): array
    {
        $endpoints = [
            Endpoint::route('oidc.token', 'post', [
                'summary' => 'Issue or exchange tokens',
                'description' => 'Use the realm host as the issuer. Authenticate with the client’s registered method: HTTP Basic (URL-encode the identifier and secret before Base64 encoding), client_id/client_secret in the form, or client_id alone for public clients. Do not combine Basic and a form secret. Token exchange requires realm support and an eligible confidential or trusted first-party client.',
                'tags' => ['Auth'],
                'security' => [['clientSecretBasic' => []], new stdClass],
                'requestBody' => ProtocolDocumentation::body('OAuthTokenRequest'),
                'responses' => [
                    200 => ProtocolDocumentation::response('Issued tokens. Refresh tokens and ID tokens depend on the grant and granted scopes.', 'OAuthTokenResponse') + [
                        'headers' => [
                            'Cache-Control' => ['schema' => ['type' => 'string', 'enum' => ['no-store']]],
                            'Pragma' => ['schema' => ['type' => 'string', 'enum' => ['no-cache']]],
                        ],
                    ],
                    500 => ProtocolDocumentation::response('Token issuance failed (server_error).', 'OAuthError'),
                ] + ProtocolDocumentation::clientErrors(),
            ]),
            Endpoint::route('oidc.introspect', 'post', [
                'summary' => 'Inspect a token',
                'description' => 'Requires a confidential client using its registered client_secret_basic or client_secret_post method. Unknown, expired, revoked and inaccessible tokens return active: false.',
                'tags' => ['Auth'],
                'security' => [['clientSecretBasic' => []], new stdClass],
                'requestBody' => ProtocolDocumentation::body('OAuthPresentedToken'),
                'responses' => [200 => ProtocolDocumentation::response('Token activity and visible metadata.', 'OAuthIntrospection')] + ProtocolDocumentation::clientErrors(),
            ]),
            Endpoint::route('oidc.revoke', 'post', [
                'summary' => 'Revoke a token',
                'description' => 'Authenticate with the registered Basic, form-secret or public-client method. Unknown tokens and tokens owned by another client also return an empty success response.',
                'tags' => ['Auth'],
                'security' => [['clientSecretBasic' => []], new stdClass],
                'requestBody' => ProtocolDocumentation::body('OAuthPresentedToken'),
                'responses' => [200 => ['description' => 'Revocation processed; the response body is empty.']] + ProtocolDocumentation::clientErrors(),
            ]),
            Endpoint::route('oidc.register', 'post', [
                'summary' => 'Register an OAuth client',
                'description' => 'Dynamic client registration must be enabled for the realm. Registers authorization-code clients, optionally with refresh tokens. Unknown metadata is ignored.',
                'tags' => ['Auth'],
                'security' => [],
                'requestBody' => ProtocolDocumentation::body('OAuthClientRegistration', 'application/json'),
                'responses' => [
                    200 => null,
                    201 => ProtocolDocumentation::response('Registered metadata; a confidential client also receives its secret.', 'OAuthRegisteredClient'),
                    400 => ProtocolDocumentation::response('invalid_redirect_uri or invalid_client_metadata.', 'OAuthError'),
                    404 => ['description' => 'Dynamic registration is disabled for this realm.'],
                    422 => null,
                    429 => ['description' => 'Rate limit exceeded.'],
                ],
            ]),
        ];

        foreach (['get', 'post'] as $method) {
            $authorize = [
                'summary' => 'Authorize a client',
                'description' => 'Browser authorization-code flow with S256 PKCE. Uses the identity session and may redirect to sign-in, required actions or consent. Success redirects to the registered callback with code, iss and the original state. After validating the client and callback, protocol errors also redirect there with error, error_description, iss and state. request and request_uri are unsupported; only response_mode=query is supported. POST requires the browser’s CSRF token.',
                'tags' => ['Auth'],
                'security' => [],
                'responses' => ProtocolDocumentation::browserResponses(),
            ];
            $logout = [
                'summary' => 'End the identity session',
                'description' => 'Browser logout, optionally with a confirmation page. A post-logout redirect is used only when registered to the identified client; state is echoed on that redirect. Invalid ID token hints are ignored. POST requires the browser’s CSRF token.',
                'tags' => ['Auth'],
                'security' => [],
                'responses' => ProtocolDocumentation::browserResponses(),
            ];

            if ($method === 'get') {
                $authorize['parameters'] = ProtocolDocumentation::queryParameters($this->authorizationProperties(), $this->authorizationRequired());
                $authorize['requestBody'] = null;
                $logout['parameters'] = ProtocolDocumentation::queryParameters($this->logoutProperties());
                $logout['requestBody'] = null;
            } else {
                $authorize['requestBody'] = ProtocolDocumentation::body('OAuthAuthorizationRequest');
                $logout['requestBody'] = ProtocolDocumentation::body('OAuthLogoutRequest');
                $logout['requestBody']['required'] = false;
                $authorize['responses'][419] = ['description' => 'The browser CSRF token is missing or expired.'];
                $logout['responses'][419] = ['description' => 'The browser CSRF token is missing or expired.'];
            }

            $endpoints[] = Endpoint::route('oidc.authorize', $method, $authorize);
            $endpoints[] = Endpoint::route('oidc.logout', $method, $logout);
        }

        foreach (['oidc.approve' => 'post', 'oidc.deny' => 'delete'] as $route => $method) {
            $endpoints[] = Endpoint::route($route, $method, [
                'summary' => $method === 'post' ? 'Approve authorization consent' : 'Deny authorization consent',
                'description' => 'Browser consent submission. Requires the signed-in identity session, its CSRF token and the single-use auth_token from the consent page. Approval redirects with an authorization code; denial redirects with access_denied.',
                'tags' => ['Auth'],
                'security' => [],
                'requestBody' => ProtocolDocumentation::body('OAuthConsentRequest'),
                'responses' => [
                    200 => null,
                    403 => ['description' => 'The consent auth_token is invalid or has already been used.'],
                    419 => ['description' => 'The browser CSRF token is missing or expired.'],
                ] + ProtocolDocumentation::browserResponses(),
            ]);
        }

        return $endpoints;
    }

    public function schemas(): array
    {
        $string = ['type' => 'string'];
        $credentials = [
            'client_id' => $string + ['description' => 'Required for form-secret and public-client authentication. Omit when using HTTP Basic.'],
            'client_secret' => $string + ['description' => 'Required only for client_secret_post. Never combine with HTTP Basic.'],
        ];
        $common = $credentials + [
            'scope' => $string + ['description' => 'Space-delimited scopes for client credentials, refresh or exchange. Refresh and exchange can only narrow granted scopes; authorization-code redemption uses the approved scopes.'],
            'resource' => $this->resourceProperty(),
        ];
        $grants = [
            'AuthorizationCodeRequest' => [
                'title' => 'Authorization code',
                'required' => ['grant_type', 'code', 'code_verifier'],
                'properties' => $common + [
                    'grant_type' => $string + ['enum' => ['authorization_code']],
                    'code' => $string,
                    'code_verifier' => $string + ['minLength' => 43, 'maxLength' => 128, 'pattern' => '^[A-Za-z0-9._~-]+$'],
                    'redirect_uri' => $string + ['format' => 'uri', 'description' => 'Required if supplied in the authorization request; must match it exactly.'],
                ],
            ],
            'RefreshTokenRequest' => [
                'title' => 'Refresh token',
                'required' => ['grant_type', 'refresh_token'],
                'properties' => $common + ['grant_type' => $string + ['enum' => ['refresh_token']], 'refresh_token' => $string],
            ],
            'ClientCredentialsRequest' => [
                'title' => 'Client credentials',
                'required' => ['grant_type'],
                'properties' => $common + ['grant_type' => $string + ['enum' => ['client_credentials']]],
            ],
            'TokenExchangeRequest' => [
                'title' => 'Token exchange',
                'required' => ['grant_type', 'subject_token', 'subject_token_type'],
                'anyOf' => [['required' => ['audience']], ['required' => ['resource']]],
                'properties' => ['resource' => $string + ['format' => 'uri', 'description' => 'Single target audience. Must agree with audience when both are supplied.']] + $common + [
                    'grant_type' => $string + ['enum' => ['urn:ietf:params:oauth:grant-type:token-exchange']],
                    'subject_token' => $string,
                    'subject_token_type' => $string + ['enum' => ['urn:ietf:params:oauth:token-type:access_token']],
                    'requested_token_type' => $string + ['enum' => ['urn:ietf:params:oauth:token-type:access_token']],
                    'audience' => $string + ['description' => 'Required unless resource is supplied; when both are supplied, they must match.'],
                ],
                'description' => 'actor_token and actor_token_type are unsupported. Additional grant parameters may be consumed by configured token-exchange extensions.',
            ],
        ];

        $mapping = [];
        foreach ($grants as $name => $grant) {
            $mapping[$grant['properties']['grant_type']['enum'][0]] = '#/components/schemas/'.$name;
        }

        return [
            'OAuthError' => [
                'type' => 'object', 'required' => ['error', 'error_description'],
                'properties' => ['error' => $string, 'error_description' => $string],
            ],
            'OAuthTokenRequest' => [
                'type' => 'object',
                'oneOf' => array_map(fn (string $reference): array => ['$ref' => $reference], array_values($mapping)),
                'discriminator' => ['propertyName' => 'grant_type', 'mapping' => $mapping],
            ],
            ...array_map(fn (array $grant): array => ['type' => 'object', ...$grant], $grants),
            'OAuthTokenResponse' => [
                'type' => 'object', 'required' => ['access_token', 'token_type', 'expires_in'],
                'properties' => [
                    'access_token' => $string,
                    'token_type' => $string + ['enum' => ['Bearer']],
                    'expires_in' => ['type' => 'integer', 'description' => 'Access-token lifetime in seconds.'],
                    'scope' => $string + ['description' => 'Space-delimited granted scopes; omitted when empty.'],
                    'refresh_token' => $string + ['description' => 'Present when a refresh token is issued or rotated.'],
                    'id_token' => $string + ['description' => 'Present when the grant issues an OpenID Connect ID token.'],
                    'issued_token_type' => $string + ['enum' => ['urn:ietf:params:oauth:token-type:access_token'], 'description' => 'Present for token exchange.'],
                ],
                'additionalProperties' => true,
            ],
            'OAuthPresentedToken' => [
                'type' => 'object', 'required' => ['token'],
                'properties' => $credentials + [
                    'token' => $string,
                    'token_type_hint' => $string + ['description' => 'access_token or refresh_token; unrecognized hints are ignored.'],
                ],
            ],
            'OAuthIntrospection' => [
                'type' => 'object', 'required' => ['active'],
                'properties' => [
                    'active' => ['type' => 'boolean'],
                    'token_type' => $string + ['enum' => ['Bearer']],
                    'scope' => $string, 'client_id' => $string, 'sub' => $string,
                    'exp' => ['type' => 'integer'], 'iat' => ['type' => 'integer'], 'nbf' => ['type' => 'integer'],
                    'jti' => $string, 'iss' => $string + ['format' => 'uri'],
                    'aud' => ['type' => 'array', 'items' => $string],
                ],
                'description' => 'Inactive responses contain only active=false. Other fields are present only for an active token when applicable; service tokens have no sub.',
            ],
            'OAuthAuthorizationRequest' => [
                'type' => 'object', 'required' => $this->authorizationRequired(),
                'properties' => $this->authorizationProperties() + ['_token' => $string + ['description' => 'Browser CSRF token, alternatively sent using X-CSRF-TOKEN.']],
            ],
            'OAuthLogoutRequest' => [
                'type' => 'object',
                'properties' => $this->logoutProperties() + [
                    'logout_confirmation' => $string + ['description' => 'Confirmation token returned by the logout page.'],
                    '_token' => $string + ['description' => 'Browser CSRF token, alternatively sent using X-CSRF-TOKEN.'],
                ],
            ],
            'OAuthConsentRequest' => [
                'type' => 'object', 'required' => ['auth_token'],
                'properties' => [
                    'auth_token' => $string + ['description' => 'Single-use token from the consent page, bound to the identity session.'],
                    '_token' => $string + ['description' => 'Browser CSRF token, alternatively sent using X-CSRF-TOKEN.'],
                ],
            ],
            'OAuthClientRegistration' => [
                'type' => 'object', 'required' => ['redirect_uris'],
                'properties' => $this->registrationProperties(),
            ],
            'OAuthRegisteredClient' => [
                'type' => 'object',
                'required' => ['client_id', 'client_id_issued_at', 'client_name', 'redirect_uris', 'post_logout_redirect_uris', 'grant_types', 'response_types', 'token_endpoint_auth_method'],
                'properties' => $this->registrationProperties() + [
                    'client_id' => $string, 'client_id_issued_at' => ['type' => 'integer'],
                    'client_secret' => $string + ['description' => 'Returned only for a confidential client; store securely.'],
                    'client_secret_expires_at' => ['type' => 'integer', 'enum' => [0], 'description' => 'Returned with a client secret; zero means it does not expire.'],
                    'scope' => $string + ['description' => 'Assigned scopes, when the registered client has a concrete nonempty scope set.'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function resourceProperty(): array
    {
        return [
            'oneOf' => [
                ['type' => 'string', 'format' => 'uri'],
                ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'uri']],
            ],
            'description' => 'Absolute protected-resource URI without a fragment, or an array of such URIs using resource[] fields. Authorization and client-credentials requests default to the realm audience. Code and refresh grants preserve the original audiences when omitted, or narrow them when supplied.',
        ];
    }

    /** @return list<string> */
    private function authorizationRequired(): array
    {
        return ['client_id', 'response_type', 'code_challenge', 'code_challenge_method'];
    }

    /** @return array<string, array<string, mixed>> */
    private function authorizationProperties(): array
    {
        $string = ['type' => 'string'];

        return [
            'client_id' => $string,
            'response_type' => $string + ['enum' => ['code']],
            'redirect_uri' => $string + ['format' => 'uri', 'description' => 'Registered callback. May be omitted only when the client has exactly one registered redirect URI.'],
            'scope' => $string + ['description' => 'Space-delimited scopes; include openid for OpenID Connect.'],
            'state' => $string + ['description' => 'Opaque client state echoed on the callback.'],
            'resource' => $this->resourceProperty(),
            'code_challenge' => $string + ['description' => 'Base64url-encoded SHA-256 digest of the PKCE verifier.'],
            'code_challenge_method' => $string + ['enum' => ['S256']],
            'nonce' => $string + ['description' => 'Nonce bound to the issued ID token.'],
            'prompt' => $string + ['description' => 'Space-delimited none, login, consent or select_account. none must be used alone.'],
            'max_age' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Maximum acceptable age of authentication in seconds.'],
            'acr_values' => $string + ['description' => 'Space-delimited requested authentication context values.'],
            'id_token_hint' => $string,
            'response_mode' => $string + ['enum' => ['query']],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function logoutProperties(): array
    {
        $string = ['type' => 'string'];

        return [
            'id_token_hint' => $string + ['description' => 'Previously issued ID token identifying the client and session.'],
            'client_id' => $string + ['description' => 'Client identifier; must agree with a valid ID token hint.'],
            'post_logout_redirect_uri' => $string + ['format' => 'uri', 'description' => 'Must be registered for the identified client.'],
            'state' => $string + ['description' => 'Echoed on an accepted post-logout redirect.'],
            'logout_hint' => $string + ['description' => 'Accepted but currently ignored.'],
            'ui_locales' => $string + ['description' => 'Accepted but currently ignored.'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function registrationProperties(): array
    {
        return [
            'redirect_uris' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string', 'format' => 'uri']],
            'client_name' => ['type' => 'string'],
            'post_logout_redirect_uris' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'uri']],
            'grant_types' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['authorization_code', 'refresh_token']], 'default' => ['authorization_code', 'refresh_token'], 'description' => 'Must include authorization_code.'],
            'response_types' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['code']], 'default' => ['code']],
            'token_endpoint_auth_method' => ['type' => 'string', 'enum' => ['none', 'client_secret_basic', 'client_secret_post'], 'default' => 'none'],
            'backchannel_logout_uri' => ['type' => 'string', 'format' => 'uri', 'description' => 'HTTPS URI without a fragment.'],
            'backchannel_logout_session_required' => ['type' => 'boolean'],
        ];
    }
}
