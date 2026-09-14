<?php

declare(strict_types=1);

namespace App\Realms\Support;

/**
 * Keyed with APP_KEY, so only this instance and its replicas give the same
 * answer.
 */
final class DomainCheckToken
{
    public static function for(string $host): string
    {
        return hash_hmac('sha256', 'lock-domain-check|'.strtolower($host), (string) config('app.key'));
    }
}
