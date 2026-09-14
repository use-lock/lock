<?php

declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\Models\User;
use App\Auth\Support\BrowserSession;
use App\Auth\Support\BrowserSessions;
use Illuminate\Support\Facades\DB;
use Lock\Server\Sessions\OidcSessionRepository;

final readonly class EndBrowserSession
{
    public function __construct(
        private BrowserSessions $sessions,
        private OidcSessionRepository $oidcSessions,
        private EndUserSessions $endUserSessions,
    ) {}

    public function handle(User $user, string $sessionId): bool
    {
        $session = $this->sessions->find($user, $sessionId);

        if (! $session instanceof BrowserSession) {
            return false;
        }

        DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'))
            ->where('id', $session->id)
            ->delete();

        $sid = $this->oidcSessions->findByBrowserSession($session->id)?->id;

        if ($sid !== null) {
            $this->endUserSessions->handle($user, $sid);
        }

        return true;
    }
}
