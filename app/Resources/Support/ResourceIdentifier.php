<?php
declare(strict_types=1);

namespace App\Resources\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ResourceIdentifier implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $identifier = trim($value);

        if (parse_url($identifier, PHP_URL_SCHEME) === null) {
            if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._~-]*(\/[A-Za-z0-9._~-]+)*\z/', $identifier) !== 1) {
                $fail(__('resources.validation.identifier-format'));
            }

            return;
        }

        $parts = parse_url($identifier);

        if (! is_array($parts) || (string) ($parts['host'] ?? '') === '' || array_key_exists('fragment', $parts)) {
            $fail(__('resources.validation.identifier-uri'));
        }
    }
}
