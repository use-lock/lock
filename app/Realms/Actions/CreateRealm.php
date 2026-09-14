<?php
declare(strict_types=1);

namespace App\Realms\Actions;

use App\Realms\Data\CreateRealmData;
use App\Realms\Data\RealmSettings;
use App\Realms\Enums\RealmAdminEvent;
use App\Realms\Models\Realm;
use App\Realms\Support\RealmConfiguration;
use App\Realms\Support\RealmDomain;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Lock\Server\SigningKeys\SigningKeyGenerator;
use Lock\Server\SigningKeys\SigningKeyStore;
use Spatie\LaravelData\Optional;

final readonly class CreateRealm
{
    public function __construct(
        private SigningKeyGenerator $keys,
        private SigningKeyStore $store,
        private CheckRealmDomain $checkDomain,
    ) {}

    /**
     * A realm without a signing key can neither mint a token nor serve a JWKS,
     * so it gets its own keypair here.
     *
     * The domain and the configuration are checked before the row exists, which
     * is what keeps a submission the rules reject from leaving a realm behind.
     */
    public function handle(CreateRealmData $data): Realm
    {
        Validator::make(['domain' => $data->domain], ['domain' => RealmDomain::rules()])->validate();

        $settings = $this->resolveSettings($data);
        $deviations = $settings->deviations();

        $realm = DB::transaction(function () use ($data, $settings, $deviations): Realm {
            $realm = Realm::create([
                'name' => $data->name,
                'slug' => $data->slug,
                'domain' => $data->domain,
                'settings' => $settings,
            ]);

            $realm->runAsCurrent(fn () => $this->store->rotate($this->keys->generate()));

            Audit::record(RealmAdminEvent::RealmCreated, $realm, context: $deviations === [] ? [] : ['settings' => $deviations]);

            return $realm;
        });

        $this->checkDomain->handle($realm);

        return $realm;
    }

    /**
     * A new realm has no configuration to merge a submission into, so the
     * defaults stand in for one: the submission is validated against the whole
     * set, which is where the rules comparing two settings find the values it
     * left out, and the DTO normalises every value to the type its setting
     * holds.
     */
    private function resolveSettings(CreateRealmData $data): RealmSettings
    {
        $submitted = $data->settings instanceof Optional ? [] : $data->settings->submitted();

        if ($submitted === []) {
            return RealmSettings::defaults();
        }

        $validated = RealmConfiguration::validate([...RealmConfiguration::defaults(), ...$submitted], $data->slug);

        return RealmSettings::defaults()->merge($validated);
    }
}
