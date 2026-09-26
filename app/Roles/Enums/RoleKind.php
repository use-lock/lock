<?php

declare(strict_types=1);

namespace App\Roles\Enums;

/**
 * Where a role applies. A realm role is held across the whole realm. The
 * tenant kinds only count inside a tenant: a preset is offered in every tenant
 * of the realm without being copied, so a change to its scopes reaches all of
 * them at once, the owner preset is the one a tenant's creator receives, and a
 * tenant role belongs to exactly one tenant.
 */
enum RoleKind: string
{
    case Realm = 'realm';
    case TenantPreset = 'tenant_preset';
    case TenantOwner = 'tenant_owner';
    case Tenant = 'tenant';

    public function appliesInTenants(): bool
    {
        return $this !== self::Realm;
    }

    public function belongsToOneTenant(): bool
    {
        return $this === self::Tenant;
    }
}
