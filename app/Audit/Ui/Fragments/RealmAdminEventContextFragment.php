<?php
declare(strict_types=1);

namespace App\Audit\Ui\Fragments;

use App\Admin\Enums\ManagementScope;
use App\Audit\Models\AdminEvent;
use App\Realms\Models\Realm;
use Lattice\Core\Attributes\AsFragment;

#[AsFragment('admin.realm-admin-event-context', can: ManagementScope::AdminEventsRead)]
final class RealmAdminEventContextFragment extends AdminEventContextFragment
{
    protected function adminEvent(): ?AdminEvent
    {
        $realm = $this->contextModelOrNull('realm', Realm::class);

        return $realm instanceof Realm ? AdminEvent::forRealm($realm)->find($this->contextString('adminEvent')) : null;
    }
}
