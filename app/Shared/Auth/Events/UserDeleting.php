<?php
declare(strict_types=1);

namespace App\Shared\Auth\Events;

use App\Auth\Models\User;
use Lock\Server\Shared\Maintenance\UserDeleting as UserDeletingContract;

final readonly class UserDeleting implements UserDeletingContract
{
    public function __construct(private User $user) {}

    public function user(): User
    {
        return $this->user;
    }
}
