<?php
declare(strict_types=1);

namespace App\Shared\Audit\Events;

use App\Realms\Models\Realm;
use App\Shared\Audit\Contracts\AdminEventType;
use Illuminate\Database\Eloquent\Model;

/**
 * One event for the whole admin trail: the type enum carries what happened, so
 * a domain records an action without a class per action. A listener turns it
 * into a row; anything else that has to react to administration hangs off the
 * same event.
 */
final readonly class AdminActionPerformed
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public AdminEventType $type,
        public Model $subject,
        public ?Realm $realm = null,
        public array $context = [],
    ) {}
}
