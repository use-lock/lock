<?php
declare(strict_types=1);

namespace App\Clients\Ui\Concerns;

use App\Clients\Data\ClientAttributes;
use App\Clients\Data\CreateClientData;
use App\Clients\Data\UpdateClientData;
use App\Clients\Support\AbsoluteUris;
use App\Clients\Support\ClientConfiguration;
use App\Clients\Support\ClientIsConsistent;
use Closure;
use Illuminate\Validation\Rule;
use Lattice\Form\Components\CheckboxGroup;
use Lattice\Form\Components\Field;
use Lattice\Form\Components\Repeater;
use Lattice\Form\Components\Select;
use Lattice\Form\Components\TextInput;
use Lattice\Form\Components\Toggle;
use Lattice\Form\FormData;
use Lock\Server\Clients\Models\Client;
use Lock\Server\Shared\Clients\TokenEndpointAuthMethod;

/**
 * Type, grant types and redirect URIs constrain each other, so each carries the
 * whole-client rule and a single-field edit is checked against the stored
 * client.
 */
trait BuildsClientFields
{
    /** @var list<string> */
    private const array CREATION_FIELDS = [
        'name',
        'token_endpoint_auth_method',
        'grant_types',
        'redirect_uris',
        'post_logout_redirect_uris',
    ];

    /**
     * @return array<string, Field>
     */
    private function clientFields(?Client $client): array
    {
        return [
            'name' => TextInput::make('name', __('clients.fields.name.label'))
                ->value($client?->name, editable: true)
                ->required()->rules(['string', 'max:255']),
            'token_endpoint_auth_method' => Select::make('token_endpoint_auth_method', __('clients.fields.type.label'))
                ->value($client?->token_endpoint_auth_method->value ?? TokenEndpointAuthMethod::ClientSecretBasic->value, editable: true)
                ->options(array_map(
                    fn (TokenEndpointAuthMethod $method): mixed => Select::option(__('clients.types.'.str_replace('_', '-', $method->value)), $method->value),
                    TokenEndpointAuthMethod::cases(),
                ))
                ->helperText(__('clients.fields.type.help-text'))
                ->required()->rules([Rule::enum(TokenEndpointAuthMethod::class)])
                ->rules(fn (FormData $data): array => [$this->clientConsistencyRule($data, $client)]),
            'grant_types' => CheckboxGroup::make('grant_types', __('clients.fields.grant-types.label'))
                ->value($client->grant_types ?? ['authorization_code', 'refresh_token'], editable: true)
                ->options(array_map(
                    fn (string $grantType): mixed => CheckboxGroup::option(__('clients.grant-types.'.str_replace('_', '-', $grantType)), $grantType),
                    ClientAttributes::GRANT_TYPES,
                ))
                ->helperText(__('clients.fields.grant-types.help-text'))
                ->required()->rules(['array', 'min:1', Rule::in(ClientAttributes::GRANT_TYPES)])
                ->rules(fn (FormData $data): array => [$this->clientConsistencyRule($data, $client)]),
            'redirect_uris' => $this->uriRepeater('redirect_uris', 'redirect-uris', $client->redirect_uris ?? [])
                ->required(in_array('authorization_code', $client->grant_types ?? [], true))
                ->dependsOn(['grant_types'], fn (Repeater $field, FormData $data): Repeater => $field
                    ->required(in_array('authorization_code', $this->clientValues($data, $client)['grant_types'], true)))
                ->message('required', __('clients.validation.redirect-uri-required')),
            'post_logout_redirect_uris' => $this->uriRepeater('post_logout_redirect_uris', 'post-logout-redirect-uris', $client->post_logout_redirect_uris ?? []),
            'consent_required' => Toggle::make('consent_required', __('clients.fields.consent-required.label'))
                ->value($client->consent_required ?? true, editable: true)
                ->helperText(__('clients.fields.consent-required.help-text'))
                ->rules(['boolean']),
            'backchannel_logout_uri' => TextInput::make('backchannel_logout_uri', __('clients.fields.backchannel-logout-uri.label'))
                ->value($client?->backchannel_logout_uri, editable: true)
                ->helperText(__('clients.fields.backchannel-logout-uri.help-text'))
                ->rules(['nullable', 'string', 'max:2048', 'url:http,https']),
        ];
    }

    /**
     * @return array<int, Field>
     */
    private function clientCreationFields(): array
    {
        return array_values(array_intersect_key($this->clientFields(null), array_flip(self::CREATION_FIELDS)));
    }

    private function clientAttributes(FormData $data, ?Client $client): ClientAttributes
    {
        $values = $this->submittedClientValues($data);

        return $client instanceof Client
            ? UpdateClientData::from($values)->toClientAttributes($client)
            : CreateClientData::from(ClientConfiguration::merge($values))->toClientAttributes();
    }

    /** @return array<string, mixed> */
    private function clientValues(FormData $data, ?Client $client): array
    {
        return ClientConfiguration::merge(
            $this->submittedClientValues($data),
            $client?->only(['name', 'token_endpoint_auth_method', 'grant_types', 'redirect_uris', 'post_logout_redirect_uris', 'consent_required', 'backchannel_logout_uri']) ?? [],
        );
    }

    /** @return array<string, mixed> */
    private function submittedClientValues(FormData $data): array
    {
        $values = $data->all();

        foreach (['redirect_uris', 'post_logout_redirect_uris'] as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = array_column((array) $values[$field], 'uri');
            }
        }

        if (($values['backchannel_logout_uri'] ?? null) === '') {
            $values['backchannel_logout_uri'] = null;
        }

        return $values;
    }

    /**
     * The console edits one row at a time, so the rule judges the submission
     * merged into the stored client while the submitted keys stay what decides
     * which field a problem is reported on. Wrapped in a closure because the
     * validator would otherwise hand it the raw submission through setData().
     */
    private function clientConsistencyRule(FormData $data, ?Client $client): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($data, $client): void {
            new ClientIsConsistent(array_values($data->keys()))
                ->setData($this->clientValues($data, $client))
                ->validate($attribute, $value, $fail);
        };
    }

    /**
     * @param  array<int, string>  $uris
     */
    private function uriRepeater(string $name, string $translation, array $uris): Repeater
    {
        return Repeater::make($name, __('clients.fields.'.$translation.'.label'))
            ->value($this->uriRows($uris), editable: true)
            ->defaultItems(0)
            ->rules(['array', 'max:'.ClientConfiguration::URI_LIMIT])
            ->reorderable(false)
            ->addLabel(__('clients.fields.'.$translation.'.add'))
            ->helperText(__('clients.fields.'.$translation.'.help-text'))
            ->schema([
                TextInput::make('uri', 'URI')
                    ->required()->rules(['string', 'max:2048', 'not_regex:/[\\r\\n]/', new AbsoluteUris]),
            ]);
    }

    /**
     * @param  array<int, string>  $uris
     * @return list<array{uri: string}>
     */
    private function uriRows(array $uris): array
    {
        return array_map(fn (string $uri): array => ['uri' => $uri], array_values($uris));
    }
}
