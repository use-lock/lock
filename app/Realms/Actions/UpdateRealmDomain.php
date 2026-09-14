<?php

declare(strict_types=1);

namespace App\Realms\Actions;

use App\Realms\Enums\RealmAdminEvent;
use App\Realms\Enums\RealmDomainStatus;
use App\Realms\Models\Realm;
use App\Realms\Support\RealmDomain;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UpdateRealmDomain
{
    public function handle(Realm $realm, string $domain): void
    {
        if ($realm->isMaster()) {
            throw ValidationException::withMessages(['domain' => [__('realms.domain.master-fixed')]]);
        }

        Validator::make(['domain' => $domain], ['domain' => RealmDomain::rules($realm)])->validate();

        if ($domain === $realm->domain) {
            return;
        }

        DB::transaction(function () use ($realm, $domain): void {
            $before = $realm->domain;

            $realm->forceFill([
                'domain' => $domain,
                'domain_status' => RealmDomainStatus::Pending,
                'domain_checked_at' => null,
                'domain_check_error' => null,
            ])->save();

            Audit::record(RealmAdminEvent::RealmUpdated, $realm, $realm, ['changes' => ['domain' => ['old' => $before, 'new' => $domain]]]);
        });
    }
}
