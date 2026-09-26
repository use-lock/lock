<?php

declare(strict_types=1);

namespace App\Tenants;

use App\Shared\Tenants\Contracts\ProvisionsPersonalTenant;
use App\Tenants\Actions\ProvisionPersonalTenant;
use App\Tenants\Models\Membership;
use App\Tenants\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

final class TenantsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProvisionsPersonalTenant::class, ProvisionPersonalTenant::class);
    }

    public function boot(): void
    {
        Relation::morphMap(['tenant' => Tenant::class, 'tenant-membership' => Membership::class]);
    }
}
