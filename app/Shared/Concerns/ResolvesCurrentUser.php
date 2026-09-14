<?php
declare(strict_types=1);

namespace App\Shared\Concerns;

use App\Auth\Models\User;
use App\Shared\Auth\Support\RequestUser;

trait ResolvesCurrentUser
{
    protected function currentUser(): User
    {
        $user = RequestUser::current();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
