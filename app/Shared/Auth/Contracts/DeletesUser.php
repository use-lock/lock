<?php
declare(strict_types=1);

namespace App\Shared\Auth\Contracts;

use App\Auth\Models\User;

interface DeletesUser
{
    public function handle(User $user): void;
}
