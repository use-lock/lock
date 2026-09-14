<?php

declare(strict_types=1);

namespace App\Resources;

use App\Admin\Enums\ManagementScope;
use App\Realms\Models\Realm;
use App\Resources\Models\Resource;
use App\Resources\Models\ResourceScope;
use App\Resources\Support\RealmResources;
use App\Resources\Ui\Pages\RealmResourcesPage;
use App\Shared\Resources\Contracts\ProvidesRealmResources;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Lattice\Core\Facades\Lattice;
use Lattice\Core\Support\Affix;
use Lattice\Ui\Components\MenuItem;

final class ResourcesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(RealmResources::class);
        $this->app->bind(ProvidesRealmResources::class, RealmResources::class);
    }

    public function boot(): void
    {
        Relation::morphMap(['resource' => Resource::class, 'resource-scope' => ResourceScope::class]);

        Lattice::extend(
            'admin.sidebar.realm',
            static fn (Realm $realm): MenuItem => MenuItem::fromPage(RealmResourcesPage::class, ['realm' => $realm->slug])
                ->label(__('navigation.resources'))
                ->prefix(Affix::icon('layers'))
                ->can(ManagementScope::ResourcesRead),
            priority: 40,
        );

        Lattice::context('resource', fn (string $value): Resource => Resource::query()->findOrFail($value));
        Lattice::context('resourceScope', fn (string $value): ResourceScope => ResourceScope::query()->findOrFail($value));
    }
}
