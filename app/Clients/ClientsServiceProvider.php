<?php

declare(strict_types=1);

namespace App\Clients;

use App\Admin\Enums\ManagementScope;
use App\Clients\Actions\ProvisionConsoleClient;
use App\Clients\Ui\Pages\RealmClientsPage;
use App\Realms\Models\Realm;
use App\Shared\Clients\Contracts\ProvisionsConsoleClient;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Lattice\Core\Facades\Lattice;
use Lattice\Core\Support\Affix;
use Lattice\Ui\Components\MenuItem;
use Lock\Server\Clients\Models\Client;

final class ClientsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProvisionsConsoleClient::class, ProvisionConsoleClient::class);
    }

    public function boot(): void
    {
        Relation::morphMap(['client' => Client::class]);

        Lattice::extend(
            'admin.sidebar.realm',
            static fn (Realm $realm): MenuItem => MenuItem::fromPage(RealmClientsPage::class, ['realm' => $realm->slug])
                ->label(__('navigation.clients'))
                ->prefix(Affix::icon('key-round'))
                ->can(ManagementScope::ClientsRead),
            priority: 20,
        );

        Lattice::context('client', fn (string $value): Client => Client::query()->findOrFail($value));
    }
}
