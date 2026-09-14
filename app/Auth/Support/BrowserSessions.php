<?php

declare(strict_types=1);

namespace App\Auth\Support;

use App\Auth\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/** Only the database session driver records who a session belongs to. */
final class BrowserSessions
{
    public static function available(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * @return Collection<int, BrowserSession>
     */
    public function forUser(User $user): Collection
    {
        return $this->query($user)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (stdClass $row): BrowserSession => BrowserSession::fromRow($row));
    }

    public function find(User $user, string $sessionId): ?BrowserSession
    {
        $row = $this->query($user)->where('id', $sessionId)->first();

        return $row === null ? null : BrowserSession::fromRow($row);
    }

    private function query(User $user): Builder
    {
        return DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getKey());
    }
}
