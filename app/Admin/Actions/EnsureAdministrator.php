<?php
declare(strict_types=1);

namespace App\Admin\Actions;

use App\Admin\Enums\BootstrapOutcome;
use App\Auth\Enums\UserAdminEvent;
use App\Auth\Models\User;
use App\Realms\Models\Realm;
use App\Shared\Audit\Audit;
use App\Shared\Auth\Contracts\CreatesRealmUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use SensitiveParameter;

final readonly class EnsureAdministrator
{
    private const string Actor = 'console:app:bootstrap';

    public function __construct(
        private CreatesRealmUser $createUser,
        private GrantSuperAdmin $grantSuperAdmin,
    ) {}

    public function handle(string $email, string $name, #[SensitiveParameter] ?string $password): BootstrapOutcome
    {
        $realm = Realm::master();

        if ($password !== null) {
            $this->assertSatisfiesPolicy($realm, $password);
        }

        $user = $realm->users()->where('email', $email)->first();

        return $user instanceof User
            ? $this->reconcile($user, $password)
            : $this->create($realm, $name, $email, $password);
    }

    private function create(Realm $realm, string $name, string $email, #[SensitiveParameter] ?string $password): BootstrapOutcome
    {
        return DB::transaction(function () use ($realm, $name, $email, $password): BootstrapOutcome {
            $user = $this->createUser->handle($realm, $name, $email, $password, verified: true);
            $this->grantSuperAdmin->handle($user, self::Actor);

            return BootstrapOutcome::Created;
        });
    }

    private function reconcile(User $user, #[SensitiveParameter] ?string $password): BootstrapOutcome
    {
        return DB::transaction(function () use ($user, $password): BootstrapOutcome {
            $changes = [];

            if ($password !== null && ! Hash::check($password, $user->password)) {
                $user->forceFill(['password' => $password]);
                $changes[] = 'password';
            }

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()]);
                $changes[] = 'email_verified_at';
            }

            $user->save();

            if ($this->grantSuperAdmin->handle($user, self::Actor)) {
                $changes[] = 'roles';
            }

            if ($changes === []) {
                return BootstrapOutcome::Unchanged;
            }

            Audit::record(UserAdminEvent::UserUpdated, $user, context: [
                'actor' => self::Actor,
                'email' => $user->email,
                'changed' => $changes,
            ]);

            return BootstrapOutcome::Updated;
        });
    }

    /**
     * A password the realm policy rejects fails the deploy instead of landing
     * half-applied.
     */
    private function assertSatisfiesPolicy(Realm $realm, #[SensitiveParameter] string $password): void
    {
        Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', $realm->passwordPolicy()->rule()]],
            [],
            ['password' => 'LOCK_ADMIN_PASSWORD'],
        )->validate();
    }
}
