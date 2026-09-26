<?php
declare(strict_types=1);

namespace App\Tenants\Actions;

use App\Auth\Models\User;
use App\Shared\Tenants\Contracts\ProvisionsPersonalTenant;

final readonly class ProvisionPersonalTenant implements ProvisionsPersonalTenant
{
    public function __construct(private CreateTenant $createTenant) {}

    public function forNewUser(User $user): void
    {
        if ($user->realm->createsTenantOnRegistration()) {
            $this->createTenant->handle($user, $user->name, personal: true);
        }
    }
}
