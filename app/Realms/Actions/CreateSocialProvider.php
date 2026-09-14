<?php
declare(strict_types=1);

namespace App\Realms\Actions;

use App\Realms\Data\CreateSocialProviderData;
use App\Realms\Enums\RealmAdminEvent;
use App\Realms\Models\Realm;
use App\Realms\Models\RealmSocialProvider;
use App\Realms\Support\SocialProviderConfiguration;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CreateSocialProvider
{
    public function handle(Realm $realm, CreateSocialProviderData $data): RealmSocialProvider
    {
        return DB::transaction(function () use ($realm, $data): RealmSocialProvider {
            Validator::make(['key' => $data->key], [
                'key' => [Rule::unique(RealmSocialProvider::class, 'key')->where('realm_id', $realm->id)],
            ])->validate();
            $driver = $data->driver;
            $config = SocialProviderConfiguration::validate($driver, $data->config->values());
            $provider = $realm->socialProviders()->create([
                'key' => $data->key,
                'driver' => $driver,
                'enabled' => $data->enabled,
                'config' => $config,
            ]);

            Audit::record(RealmAdminEvent::SocialProviderCreated, $provider, $realm, [
                'realm' => $realm->slug,
                'key' => $provider->key,
                'driver' => $driver->value,
                'enabled' => $provider->enabled,
            ]);

            return $provider;
        });
    }
}
