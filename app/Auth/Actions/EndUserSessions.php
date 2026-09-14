<?php
declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\Models\User;
use Illuminate\Support\Facades\DB;
use Lock\Server\Sessions\Models\OidcSession;
use Lock\Server\Sessions\OidcSessionRepository;

final readonly class EndUserSessions
{
    public function __construct(
        private OidcSessionRepository $sessions,
    ) {}

    public function handle(User $user, ?string $sid = null): int
    {
        $sids = OidcSession::query()
            ->where('realm', $user->realm->slug)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->when($sid !== null, fn ($query) => $query->whereKey($sid))
            ->pluck('id');

        $user->realm->runAsCurrent(fn () => DB::transaction(function () use ($user, $sids, $sid): void {
            foreach ($sids as $revoked) {
                $this->sessions->revoke($revoked);
            }

            if ($sid === null) {
                $this->forgetBrowserSessions($user);
            }
        }));

        return $sids->count();
    }

    /**
     * Only the database driver lets the server end a browser session it holds
     * no cookie for; with any other driver the user provider refuses the user
     * on the next request.
     */
    private function forgetBrowserSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $connection = config('session.connection');

        DB::connection(is_string($connection) ? $connection : null)
            ->table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();
    }
}
