<?php
declare(strict_types=1);

namespace App\Realms\Actions;

use App\Realms\Enums\RealmAdminEvent;
use App\Realms\Models\RealmSocialProvider;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Lock\Server\Brokering\Models\SocialAccount;

final class DeleteSocialProvider
{
    public function handle(RealmSocialProvider $provider): void
    {
        DB::transaction(function () use ($provider): void {
            $realm = $provider->realm;

            /*
             * The links go with the provider: a key that is added back later
             * would otherwise silently restore logins the administrator
             * removed.
             */
            $unlinked = SocialAccount::query()
                ->inRealm($realm->slug)
                ->where('provider', $provider->key)
                ->delete();

            $provider->delete();

            Audit::record(RealmAdminEvent::SocialProviderDeleted, $provider, $realm, [
                'realm' => $realm->slug,
                'key' => $provider->key,
                'driver' => $provider->driver->value,
                'accounts-unlinked' => $unlinked,
            ]);
        });
    }
}
