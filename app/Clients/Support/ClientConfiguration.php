<?php
declare(strict_types=1);

namespace App\Clients\Support;

use App\Clients\Data\ClientAttributes;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lock\Server\Shared\Clients\TokenEndpointAuthMethod;

final class ClientConfiguration
{
    public const int URI_LIMIT = 100;

    /**
     * @param  array<string, mixed>  $values
     */
    public static function validate(array $values): void
    {
        Validator::make($values, [
            'name' => ['required', 'string', 'max:255'],
            'token_endpoint_auth_method' => ['required', Rule::enum(TokenEndpointAuthMethod::class)],
            'grant_types' => ['required', 'array', 'list', 'min:1', Rule::in(ClientAttributes::GRANT_TYPES), new ClientIsConsistent],
            'redirect_uris' => ['present', 'array', 'list', 'max:'.self::URI_LIMIT, new AbsoluteUris, new ClientIsConsistent],
            'post_logout_redirect_uris' => ['present', 'array', 'list', 'max:'.self::URI_LIMIT, new AbsoluteUris],
            'consent_required' => ['required', 'boolean'],
            'backchannel_logout_uri' => ['nullable', 'string', 'max:2048', 'url:http,https'],
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $patch
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public static function merge(array $patch, array $stored = []): array
    {
        $values = [...[
            'name' => '',
            'token_endpoint_auth_method' => TokenEndpointAuthMethod::ClientSecretBasic->value,
            'grant_types' => [],
            'redirect_uris' => [],
            'post_logout_redirect_uris' => [],
            'consent_required' => true,
            'backchannel_logout_uri' => null,
        ], ...$stored, ...$patch];

        if ($values['token_endpoint_auth_method'] instanceof TokenEndpointAuthMethod) {
            $values['token_endpoint_auth_method'] = $values['token_endpoint_auth_method']->value;
        }

        return $values;
    }
}
