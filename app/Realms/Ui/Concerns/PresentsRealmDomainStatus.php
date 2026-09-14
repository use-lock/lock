<?php
declare(strict_types=1);

namespace App\Realms\Ui\Concerns;

use App\Realms\Enums\RealmDomainStatus;
use Lattice\Core\Enums\ColorName;

trait PresentsRealmDomainStatus
{
    private function domainStatusColor(RealmDomainStatus $status): ColorName
    {
        return match ($status) {
            RealmDomainStatus::Verified => ColorName::Success,
            RealmDomainStatus::Pending => ColorName::Muted,
            RealmDomainStatus::Unreachable, RealmDomainStatus::Misrouted => ColorName::Warning,
        };
    }
}
