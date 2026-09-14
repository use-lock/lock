<?php

declare(strict_types=1);

namespace App\Realms;

use App\Admin\Enums\ManagementScope;
use App\Realms\Models\Realm;
use App\Realms\Models\RealmSocialProvider;
use App\Realms\Support\DatabaseRealmRepository;
use App\Realms\Support\RealmHostGate;
use App\Realms\Ui\Pages\RealmSettingsPage;
use App\Realms\Ui\Pages\RealmsPage;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Lattice\Core\Facades\Lattice;
use Lattice\Ui\Components\MenuItem;
use Lattice\Ui\Enums\Icon;
use Lock\Server\Realms\RealmRepository;

final class RealmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RealmRepository::class, DatabaseRealmRepository::class);
    }

    public function boot(): void
    {
        Relation::morphMap(['realm' => Realm::class, 'social-provider' => RealmSocialProvider::class]);

        Lattice::extend(
            'admin.sidebar.realm',
            static fn (Realm $realm): MenuItem => MenuItem::fromPage(RealmSettingsPage::class, ['realm' => $realm->slug])
                ->label(__('navigation.settings'))
                ->prefix(Icon::Settings)
                ->can(ManagementScope::RealmsRead),
            priority: 70,
        );

        Lattice::extend(
            'admin.sidebar.instance',
            static fn (): MenuItem => MenuItem::fromPage(RealmsPage::class)
                ->label(__('navigation.realms'))
                ->prefix(Icon::List)
                ->can(ManagementScope::RealmsRead),
            priority: 10,
        );

        /*
         * Every realm definition gates itself on a management scope, so the
         * resolver is a plain lookup.
         */
        Lattice::context(
            'realm',
            fn (string $value): Realm => Realm::where('slug', $value)->firstOrFail(),
            keyBy: fn (Realm $realm): string => $realm->slug,
        );

        Lattice::context('socialProvider', fn (string $value): RealmSocialProvider => RealmSocialProvider::query()->findOrFail($value));

        Route::matched(fn (RouteMatched $event) => app(RealmHostGate::class)->guard($event->request, $event->route));
    }
}
