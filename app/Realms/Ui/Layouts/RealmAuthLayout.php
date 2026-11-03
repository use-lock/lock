<?php

declare(strict_types=1);

namespace App\Realms\Ui\Layouts;

use App\Realms\Models\Realm;
use App\Realms\Support\RealmThemeFiles;
use Illuminate\Http\Request;
use Lattice\Core\Attributes\AsLayout;
use Lattice\Layouts\Components\Outlet;
use Lattice\Ui\Components\Component;
use Lattice\Ui\Components\Icon;
use Lattice\Ui\Components\Image;
use Lattice\Ui\Components\Stack;
use Lattice\Ui\Enums\Align;
use Lattice\Ui\Enums\Gap;
use Lattice\Ui\Enums\Height;
use Lattice\Ui\Enums\Justify;
use Lattice\Ui\Enums\Size;
use Lattice\Ui\Enums\Width;
use Lattice\Ui\PageSchema;
use Lock\Server\Shared\Ui\Layouts\AuthLayout;

/**
 * Small text fails contrast directly on background art, so art brings the panel
 * with it. A realm without a theme renders the package's layout untouched.
 */
#[AsLayout('auth')]
final class RealmAuthLayout extends AuthLayout
{
    public function schema(PageSchema $schema, Request $request): PageSchema
    {
        $realm = Realm::current();

        if (! $realm->hasTheme()) {
            return parent::schema($schema, $request);
        }

        $files = RealmThemeFiles::for($realm);
        $hasArt = $files->url($realm->theme->value('background-image-url')) !== null;

        return $schema->schema([
            Stack::make('auth-shell')
                ->height(Height::Screen)
                ->justify(Justify::Center)
                ->align(Align::Center)
                ->class('lock-auth-shell')
                ->schema([
                    Stack::make('auth-card')
                        ->width(Width::Small)
                        ->align(Align::Center)
                        ->gap(Gap::Large)
                        ->class($hasArt ? 'lock-auth-card lock-auth-panel' : 'lock-auth-card')
                        ->schema([
                            $this->logo($realm, $files),
                            Outlet::make(),
                        ]),
                ]),
        ]);
    }

    private function logo(Realm $realm, RealmThemeFiles $files): Component
    {
        $url = $files->url($realm->theme->value('logo-url'));

        if ($url === null) {
            return Icon::make((string) config('oidc-ui.brand_icon', 'logo'))->size(Size::Xl4);
        }

        return Image::make($url, 'auth-logo')
            ->alt($realm->theme->value('logo-alt') ?? $realm->name)
            ->previewable(false)
            ->class('lock-auth-logo');
    }
}
