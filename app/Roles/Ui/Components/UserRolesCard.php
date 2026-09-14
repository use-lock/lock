<?php
declare(strict_types=1);

namespace App\Roles\Ui\Components;

use App\Auth\Models\User;
use App\Roles\Ui\Actions\UpdateUserRoles;
use App\Shared\Ui\Components\ActionBar;
use Lattice\Actions\Components\Action;
use Lattice\Core\Enums\ColorName;
use Lattice\Ui\Components\Badge;
use Lattice\Ui\Components\Card;
use Lattice\Ui\Components\Link;
use Lattice\Ui\Components\Stack;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Enums\Gap;
use Lattice\Ui\Enums\Orientation;

/**
 * Hung into the user detail page's slot rather than rendered by it: roles are
 * not the user page's to own, and the page must not import them.
 */
final class UserRolesCard
{
    public static function make(User $user): Card
    {
        $realm = $user->realm;
        $names = $user->roleNames();

        return Card::make(__('users.detail.realm-roles.heading'), __('users.detail.realm-roles.subtitle'))->schema([
            Stack::make()->gap(Gap::Medium)->schema([
                Stack::make('user-realm-roles')
                    ->direction(Orientation::Horizontal)
                    ->gap(Gap::Small)
                    ->schema($names === []
                        ? [Text::make(__('users.detail.realm-roles.none'))->color(ColorName::Muted)]
                        : array_map(fn (string $name): Badge => Badge::make($name), $names)),
                $realm->roles()->exists()
                    ? ActionBar::button(Action::use(UpdateUserRoles::class))
                    : Link::make(__('users.detail.realm-roles.define'))->href(route('admin.realms.roles', ['realm' => $realm->slug], false)),
            ]),
        ]);
    }
}
