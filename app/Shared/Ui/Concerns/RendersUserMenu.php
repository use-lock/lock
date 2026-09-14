<?php
declare(strict_types=1);

namespace App\Shared\Ui\Concerns;

use App\Auth\Models\User;
use Lattice\Core\Enums\Breakpoint;
use Lattice\Core\Enums\ColorName;
use Lattice\Core\Support\Affix;
use Lattice\Ui\Components\Avatar;
use Lattice\Ui\Components\Dropdown;
use Lattice\Ui\Components\MenuItem;
use Lattice\Ui\Components\Stack;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Contracts\SchemaEntry;
use Lattice\Ui\Enums\Align;
use Lattice\Ui\Enums\AvatarShape;
use Lattice\Ui\Enums\Gap;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\Orientation;
use Lattice\Ui\Enums\Placement;
use Lattice\Ui\Enums\Size;
use Lattice\Ui\Enums\Width;

trait RendersUserMenu
{
    /**
     * @param  array<int, SchemaEntry>  $items
     */
    private function userMenu(User $user, array $items): Dropdown
    {
        return Dropdown::make('user-menu')
            ->placement(Placement::Bottom)
            ->trigger([
                Stack::make()
                    ->direction(Orientation::Horizontal)
                    ->align(Align::Center)
                    ->gap(Gap::Medium)
                    ->schema([
                        Avatar::make(key: 'user-menu-avatar')->name($user->name)->shape(AvatarShape::Rounded),
                        Stack::make()
                            ->width(Width::Fill)
                            ->gap(Gap::None)
                            ->visibleFrom(Breakpoint::Md)
                            ->schema([
                                Text::make($user->name)
                                    ->size(Size::Sm)
                                    ->color(ColorName::Default)
                                    ->hideWhenCollapsed(),
                                Text::make($user->email)
                                    ->size(Size::Xs)
                                    ->color(ColorName::Muted)
                                    ->hideWhenCollapsed(),
                            ]),
                    ]),
            ])
            ->items($items);
    }

    private function accountMenuItem(): MenuItem
    {
        return MenuItem::make(__('navigation.account'))
            ->href(route('account', absolute: false))
            ->prefix(Affix::icon('circle-user'));
    }

    /**
     * A realm page passes its realm's end-session endpoint; the default ends
     * the console session through the relying-party flow.
     */
    private function logoutMenuItem(?string $href = null): MenuItem
    {
        return MenuItem::make(__('common.action.log-out'))
            ->href($href ?? route('logout', absolute: false))
            ->prefix(Affix::icon('log-out'))
            ->method(HttpMethod::Post);
    }
}
