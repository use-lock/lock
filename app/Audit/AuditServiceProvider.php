<?php

declare(strict_types=1);

namespace App\Audit;

use App\Admin\Enums\ManagementScope;
use App\Audit\Listeners\RecordAdminEvent;
use App\Audit\Ui\Pages\AdminEventsPage;
use App\Audit\Ui\Pages\RealmUserEventsPage;
use App\Realms\Models\Realm;
use App\Shared\Audit\Events\AdminActionPerformed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lattice\Core\Facades\Lattice;
use Lattice\Core\Support\Affix;
use Lattice\Ui\Components\MenuItem;
use Lattice\Ui\Enums\Icon;

final class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(AdminActionPerformed::class, RecordAdminEvent::class);

        Lattice::extend(
            'admin.sidebar.realm',
            static fn (Realm $realm): MenuItem => MenuItem::fromPage(RealmUserEventsPage::class, ['realm' => $realm->slug])
                ->label(__('navigation.user-events'))
                ->prefix(Affix::icon('shield-alert'))
                ->can(ManagementScope::UserEventsRead),
            priority: 50,
        );

        Lattice::extend(
            'admin.sidebar.instance',
            static fn (): MenuItem => MenuItem::fromPage(AdminEventsPage::class)
                ->label(__('navigation.admin-events'))
                ->prefix(Icon::Clock)
                ->can(ManagementScope::AdminEventsRead),
            priority: 20,
        );
    }
}
