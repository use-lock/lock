<?php
declare(strict_types=1);

namespace App\Shared\Audit\Concerns;

use App\Shared\Audit\Enums\AdminEventCategory;

trait IsAdminEventType
{
    public function type(): string
    {
        return $this->value;
    }

    public function category(): AdminEventCategory
    {
        return AdminEventCategory::from(explode('.', $this->value)[0]);
    }

    public function translationKey(): string
    {
        return 'audit.admin.types.'.$this->value;
    }
}
