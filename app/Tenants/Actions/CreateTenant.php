<?php
declare(strict_types=1);

namespace App\Tenants\Actions;

use App\Auth\Models\User;
use App\Realms\Models\Realm;
use App\Roles\Enums\RoleKind;
use App\Tenants\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final readonly class CreateTenant
{
    private const int SLUG_LENGTH = 48;

    /** The creator becomes its first member and holds the realm's owner presets in it. */
    public function handle(User $owner, string $name, bool $personal = false): Tenant
    {
        $realm = $owner->realm;

        if (! $realm->hasTenancy()) {
            throw new LogicException("The realm [{$realm->slug}] has no tenancy.");
        }

        return DB::transaction(function () use ($realm, $owner, $name, $personal): Tenant {
            $tenant = $realm->tenants()->create([
                'name' => $name,
                'slug' => $this->uniqueSlug($realm, $name),
                'personal' => $personal,
            ]);

            $membership = $tenant->memberships()->create(['user_id' => $owner->id]);
            $membership->roles()->sync($realm->roles()->where('kind', RoleKind::TenantOwner)->pluck('id')->all());

            return $tenant;
        });
    }

    private function uniqueSlug(Realm $realm, string $name): string
    {
        $base = Str::limit(Str::slug($name), self::SLUG_LENGTH, '') ?: 'tenant';
        $slug = $base;

        while ($realm->tenants()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}
