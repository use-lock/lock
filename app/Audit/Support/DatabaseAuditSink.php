<?php

declare(strict_types=1);

namespace App\Audit\Support;

use App\Audit\Models\UserEvent;
use App\Realms\Models\Realm;
use Lock\Server\Audit\Contracts\AuditSink;
use Lock\Server\Shared\Audit\AuditRecord;

/**
 * The package's listener swallows whatever this throws, so a failure here shows
 * up as missing rows, never as a failed login.
 */
final class DatabaseAuditSink implements AuditSink
{
    /**
     * The record carries no realm: it is written while its realm is current.
     */
    public function record(AuditRecord $record): void
    {
        UserEvent::create([
            'realm_id' => Realm::current()->id,
            'type' => $record->type,
            'category' => $record->category(),
            'user_id' => $record->userId,
            'client_id' => $record->clientId,
            'sid' => $record->sid,
            'ip' => $record->ip,
            'user_agent' => $record->userAgent,
            'failure' => $record->failure,
            'context' => $record->context === [] ? null : $record->context,
            'occurred_at' => $record->occurredAt,
        ]);
    }
}
