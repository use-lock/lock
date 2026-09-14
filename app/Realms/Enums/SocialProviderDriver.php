<?php

declare(strict_types=1);

namespace App\Realms\Enums;

/**
 * The brokering drivers use-lock/server's SocialProviderRegistry ships. Each one
 * needs a different set of credentials, which is why the stored config is a
 * map rather than columns.
 */
enum SocialProviderDriver: string
{
    case Google = 'google';
    case Apple = 'apple';
    case GitHub = 'github';
    case Oidc = 'oidc';

    /**
     * @return list<string>
     */
    public function fields(): array
    {
        return match ($this) {
            self::Google, self::GitHub => ['client_id', 'client_secret'],
            self::Apple => ['client_id', 'team_id', 'key_id', 'private_key'],
            self::Oidc => ['issuer', 'client_id', 'client_secret'],
        };
    }

    /**
     * @return array<string, list<self>>
     */
    public static function credentials(): array
    {
        $credentials = [];

        foreach (self::cases() as $driver) {
            foreach ($driver->fields() as $field) {
                $credentials[$field][] = $driver;
            }
        }

        return $credentials;
    }

    public static function isSecret(string $field): bool
    {
        return array_any(self::cases(), fn ($driver) => in_array($field, $driver->secrets(), true));
    }

    /**
     * @return list<string>
     */
    public function secrets(): array
    {
        return match ($this) {
            self::Apple => ['private_key'],
            default => ['client_secret'],
        };
    }
}
