<?php
declare(strict_types=1);

namespace App\Shared\Tenants\Contracts;

use App\Auth\Models\User;

interface ProvisionsPersonalTenant
{
    /** Gives a newly registered user the personal tenant their realm promises them; a no-op in any other realm. */
    public function forNewUser(User $user): void;
}
