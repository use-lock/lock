<?php
declare(strict_types=1);

namespace App\Shared\Realms\Events;

use Lock\Server\Shared\Maintenance\RealmDeleting as RealmDeletingContract;

final readonly class RealmDeleting implements RealmDeletingContract
{
    public function __construct(private string $realm) {}

    public function realm(): string
    {
        return $this->realm;
    }
}
