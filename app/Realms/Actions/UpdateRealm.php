<?php
declare(strict_types=1);

namespace App\Realms\Actions;

use App\Realms\Data\UpdateRealmData;
use App\Realms\Enums\RealmAdminEvent;
use App\Realms\Models\Realm;
use App\Realms\Support\RealmConfiguration;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Optional;

final readonly class UpdateRealm
{
    public function __construct(private UpdateRealmDomain $updateDomain, private CheckRealmDomain $checkDomain) {}

    /**
     * The mirror of {@see CreateRealm}: everything the payload carries is
     * applied here, so no caller has to remember a second write.
     *
     * The submission is merged into the realm's settings DTO before anything is
     * compared, so a transport that types nothing — JSON with a quoted number,
     * an HTML form — is normalised to the type each setting holds rather than
     * leaving a string where an int belongs.
     */
    public function handle(Realm $realm, UpdateRealmData $data): Realm
    {
        $domainChanged = DB::transaction(function () use ($realm, $data): bool {
            $locked = Realm::query()->lockForUpdate()->findOrFail($realm->id);

            $before = $locked->domain;
            $this->applyConfiguration($locked, $data);

            if (! $data->domain instanceof Optional) {
                $this->updateDomain->handle($locked, $data->domain);
            }

            return $before !== $locked->domain;
        });

        $realm->refresh();

        if ($domainChanged) {
            $this->checkDomain->handle($realm);
        }

        return $realm;
    }

    /**
     * The domain carries its own audit entry and its own reset of the check
     * state, which is why it is not folded into these changes.
     */
    private function applyConfiguration(Realm $realm, UpdateRealmData $data): void
    {
        $submitted = [
            ...$data->name instanceof Optional ? [] : ['name' => $data->name],
            ...$data->settings instanceof Optional ? [] : $data->settings->submitted(),
        ];

        if ($submitted === []) {
            return;
        }

        $before = ['name' => $realm->name, ...$realm->configuration()];
        $settings = $realm->settings->merge(
            RealmConfiguration::validate([...$realm->configuration(), ...array_diff_key($submitted, ['name' => null])], $realm->slug),
        );
        $after = ['name' => $submitted['name'] ?? $realm->name, ...$settings->toArray()];

        $changes = [];

        foreach (array_keys($submitted) as $key) {
            if ($before[$key] !== $after[$key]) {
                $changes[$key] = ['old' => $before[$key], 'new' => $after[$key]];
            }
        }

        if ($changes === []) {
            return;
        }

        $realm->name = $after['name'];
        $realm->settings = $settings;
        $realm->save();

        Audit::record(RealmAdminEvent::RealmUpdated, $realm, $realm, ['changes' => $changes]);
    }
}
