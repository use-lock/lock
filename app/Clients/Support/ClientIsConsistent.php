<?php
declare(strict_types=1);

namespace App\Clients\Support;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Lock\Server\Shared\Clients\TokenEndpointAuthMethod;

/**
 * Type, grant types and redirect URIs constrain each other, so none of it fits
 * on a single property: the rule reads the whole submission and reports each
 * problem on the field that owns it. A field the submission leaves out cannot
 * carry a message of its own, so whichever field is being validated takes it
 * instead — which is what lets the console hang this on a single edited row.
 */
final class ClientIsConsistent implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * @param  list<string>|null  $submitted  the fields the caller sent, when the
     *                                        data being checked is wider than them
     */
    public function __construct(private readonly ?array $submitted = null) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach ($this->problems() as $owner => $messages) {
            if ($attribute !== $owner && $this->carries($owner)) {
                continue;
            }

            foreach ($messages as $message) {
                $fail($message);
            }
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function problems(): array
    {
        /** @var array<string, list<string>> $problems */
        $problems = [];

        $grantTypes = array_filter((array) ($this->data['grant_types'] ?? []), is_string(...));
        $method = TokenEndpointAuthMethod::tryFrom((string) ($this->data['token_endpoint_auth_method'] ?? ''));
        $usesCode = in_array('authorization_code', $grantTypes, true);

        if (in_array('refresh_token', $grantTypes, true) && ! $usesCode) {
            $problems['grant_types'][] = __('clients.validation.refresh-token-needs-code');
        }

        if (in_array('client_credentials', $grantTypes, true) && $method instanceof TokenEndpointAuthMethod && ! $method->requiresSecret()) {
            $problems['grant_types'][] = __('clients.validation.client-credentials-needs-secret');
        }

        if ($usesCode && AbsoluteUris::list($this->data['redirect_uris'] ?? []) === []) {
            $problems['redirect_uris'][] = __('clients.validation.redirect-uri-required');
        }

        return $problems;
    }

    private function carries(string $field): bool
    {
        return $this->submitted === null
            ? array_key_exists($field, $this->data)
            : in_array($field, $this->submitted, true);
    }
}
