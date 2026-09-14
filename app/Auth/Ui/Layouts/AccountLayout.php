<?php

declare(strict_types=1);

namespace App\Auth\Ui\Layouts;

use App\Auth\Models\User;
use App\Shared\Ui\Components\BrandMark;
use App\Shared\Ui\Concerns\RendersUserMenu;
use Illuminate\Http\Request;
use Lattice\Core\Attributes\AsLayout;
use Lattice\Core\Enums\Breakpoint;
use Lattice\Core\Enums\ColorName;
use Lattice\Layouts\Components\Outlet;
use Lattice\Layouts\LayoutDefinition;
use Lattice\Ui\Components\Callouts;
use Lattice\Ui\Components\Stack;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Components\Topbar;
use Lattice\Ui\Enums\Align;
use Lattice\Ui\Enums\Gap;
use Lattice\Ui\Enums\Height;
use Lattice\Ui\Enums\Orientation;
use Lattice\Ui\Enums\Side;
use Lattice\Ui\Enums\Size;
use Lattice\Ui\Enums\Width;
use Lattice\Ui\PageSchema;
use Lattice\Ui\Slot;

#[AsLayout('account')]
final class AccountLayout extends LayoutDefinition
{
    use RendersUserMenu;

    public function schema(PageSchema $schema, Request $request): PageSchema
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $realm = $user->realm;

        return $schema->schema([
            Stack::make('account-shell')
                ->height(Height::Screen)
                ->gap(Gap::None)
                ->schema([
                    Topbar::make('account-topbar')->sticky()->items([
                        Stack::make('account-topbar-start')
                            ->direction(Orientation::Horizontal)
                            ->align(Align::Center)
                            ->width(Width::Auto)
                            ->gap(Gap::Medium)
                            ->schema([
                                BrandMark::wordmark('account-brand', $this->accountUrl()),
                                Text::make('/')->size(Size::Sm)->color(ColorName::Muted)->visibleFrom(Breakpoint::Md),
                                Text::make($realm->name, 'account-realm-name')->size(Size::Sm)->visibleFrom(Breakpoint::Md),
                            ]),
                        Stack::make('account-topbar-end')
                            ->float(Side::End)
                            ->width(Width::Auto)
                            ->direction(Orientation::Horizontal)
                            ->align(Align::Center)
                            ->gap(Gap::Small)
                            ->schema([
                                $this->userMenu($user, [
                                    Slot::make('app.user-menu'),
                                    $this->logoutMenuItem(route('oidc.logout', absolute: false)),
                                ]),
                            ]),
                    ]),
                    Callouts::make('account-callouts'),
                    Stack::make('account-content')
                        ->align(Align::Center)
                        ->schema([
                            Outlet::make(),
                        ]),
                ]),
        ]);
    }

    private function accountUrl(): string
    {
        return route('account', absolute: false);
    }
}
