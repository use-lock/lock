<?php
declare(strict_types=1);

namespace App\Audit\Ui\Fragments;

use App\Admin\Enums\ManagementScope;
use App\Audit\Models\AdminEvent;
use Lattice\Core\Attributes\AsFragment;

#[AsFragment('admin.admin-event-context', can: ManagementScope::AdminEventsRead)]
final class InstanceAdminEventContextFragment extends AdminEventContextFragment
{
    protected function adminEvent(): ?AdminEvent
    {
        return AdminEvent::global()->find($this->contextString('adminEvent'));
    }
}
