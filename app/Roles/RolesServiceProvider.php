<?php

declare(strict_types=1);

namespace App\Roles;

use App\Admin\Enums\ManagementScope;
use App\Auth\Models\User;
use App\Realms\Models\Realm;
use App\Roles\Models\Role;
use App\Roles\Ui\Components\UserRolesCard;
use App\Roles\Ui\Pages\RolesPage;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Lattice\Core\Facades\Lattice;
use Lattice\Core\Support\Affix;
use Lattice\Ui\Components\Card;
use Lattice\Ui\Components\MenuItem;

final class RolesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Relation::morphMap(['role' => Role::class]);

        Lattice::extend(
            'admin.sidebar.realm',
            static fn (Realm $realm): MenuItem => MenuItem::fromPage(RolesPage::class, ['realm' => $realm->slug])
                ->label(__('navigation.roles'))
                ->prefix(Affix::icon('shield-check'))
                ->can(ManagementScope::RolesRead),
            priority: 30,
        );

        Lattice::context('role', fn (string $value): Role => Role::query()->findOrFail($value));

        Lattice::extend(
            'admin.users.detail.cards',
            static fn (User $target): Card => UserRolesCard::make($target),
        );
    }
}
