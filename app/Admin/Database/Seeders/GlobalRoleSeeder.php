<?php

declare(strict_types=1);

namespace App\Admin\Database\Seeders;

use App\Admin\ManagementApi;
use App\Realms\Models\Realm;
use App\Shared\Audit\Audit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The protected roles grant scopes of the management API resource, so the
 * resource and its scope rows have to be reconciled first. Establishing the
 * baseline is not an administrative act, so it writes no audit rows —
 * `app:bootstrap` records a reconcile that actually changes something.
 */
final class GlobalRoleSeeder extends Seeder
{
    public function run(): void
    {
        $api = app(ManagementApi::class);

        DB::transaction(fn () => Audit::withoutRecording(function () use ($api): void {
            $realm = Realm::master();

            $api->reconcile($realm);
            $api->reconcileRoles($realm);
        }));
    }
}
