<?php
declare(strict_types=1);

namespace App\Audit\Listeners;

use App\Audit\Models\AdminEvent;
use App\Shared\Audit\Events\AdminActionPerformed;
use App\Shared\Auth\Support\RequestUser;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Lock\Server\Shared\Audit\SessionContext;

/**
 * Request and session state are resolved per event, never captured, so the
 * listener stays safe under Octane.
 *
 * Unlike the security trail this one is fail-closed: it runs inside the
 * transaction of the action that raised the event, so a row that cannot be
 * written takes the change down with it.
 */
final readonly class RecordAdminEvent
{
    public function __construct(private Container $app) {}

    public function handle(AdminActionPerformed $event): void
    {
        $request = $this->request();

        AdminEvent::create([
            'realm_id' => $event->realm?->id,
            'type' => $event->type->type(),
            'category' => $event->type->category()->value,
            'user_id' => RequestUser::current()?->id,
            'subject_type' => $event->subject->getMorphClass(),
            'subject_id' => $event->subject->getKey(),
            'sid' => $this->sessionSid(),
            'ip' => $request?->ip(),
            'user_agent' => $this->userAgent($request),
            'context' => $event->context === [] ? null : $event->context,
            'occurred_at' => now(),
        ]);
    }

    private function request(): ?Request
    {
        return $this->app->bound('request') ? $this->app->make('request') : null;
    }

    private function userAgent(?Request $request): ?string
    {
        $userAgent = $request?->userAgent();

        return is_string($userAgent) && $userAgent !== '' ? mb_substr($userAgent, 0, 255) : null;
    }

    private function sessionSid(): ?string
    {
        if (! $this->app->bound('session.store') || ! $this->app->make('session.store')->isStarted()) {
            return null;
        }

        return $this->app->make(SessionContext::class)->sid();
    }
}
