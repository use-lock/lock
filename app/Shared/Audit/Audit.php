<?php
declare(strict_types=1);

namespace App\Shared\Audit;

use App\Realms\Models\Realm;
use App\Shared\Audit\Contracts\AdminEventType;
use App\Shared\Audit\Events\AdminActionPerformed;
use Illuminate\Database\Eloquent\Model;

/**
 * The seam every domain writes the admin trail through. It only raises the
 * event; what listens to it is the Audit domain's business.
 */
final class Audit
{
    /**
     * Not request state: {@see self::withoutRecording()} restores it in a
     * `finally`, so no request ever inherits it from another.
     */
    private static bool $recording = true;

    /**
     * @param  array<string, mixed>  $context
     */
    public static function record(AdminEventType $type, Model $subject, ?Realm $realm = null, array $context = []): void
    {
        if (self::$recording) {
            event(new AdminActionPerformed($type, $subject, $realm, $context));
        }
    }

    /**
     * Establishing a baseline is not an administrative act. Everything a
     * callback records here is dropped.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutRecording(callable $callback): mixed
    {
        self::$recording = false;

        try {
            return $callback();
        } finally {
            self::$recording = true;
        }
    }
}
