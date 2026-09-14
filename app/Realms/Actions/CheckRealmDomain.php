<?php

declare(strict_types=1);

namespace App\Realms\Actions;

use App\Realms\Enums\RealmDomainStatus;
use App\Realms\Models\Realm;
use App\Realms\Support\DomainCheckToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Asking the domain for this instance's token proves that DNS, the proxy and
 * TLS all lead here, which a DNS lookup cannot. Redirects are not followed, so
 * a domain that merely redirects here does not pass.
 */
final class CheckRealmDomain
{
    public function handle(Realm $realm): RealmDomainStatus
    {
        [$status, $error] = $this->probe($realm);

        $realm->forceFill([
            'domain_status' => $status,
            'domain_checked_at' => now(),
            'domain_check_error' => $error === null ? null : Str::limit($error, 480),
        ])->save();

        return $status;
    }

    /** @return array{RealmDomainStatus, string|null} */
    private function probe(Realm $realm): array
    {
        try {
            $response = Http::connectTimeout(3)
                ->timeout(5)
                ->withoutRedirecting()
                ->get($realm->origin().route('realm.domain-check', absolute: false));
        } catch (ConnectionException $exception) {
            return [RealmDomainStatus::Unreachable, $exception->getMessage()];
        }

        if (! $response->ok()) {
            return [RealmDomainStatus::Misrouted, 'HTTP '.$response->status()];
        }

        return hash_equals(DomainCheckToken::for($realm->host()), trim($response->body()))
            ? [RealmDomainStatus::Verified, null]
            : [RealmDomainStatus::Misrouted, null];
    }
}
