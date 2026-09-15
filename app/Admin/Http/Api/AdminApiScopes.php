<?php
declare(strict_types=1);

namespace App\Admin\Http\Api;

use App\Admin\Enums\ApiResource;
use App\Admin\Enums\ManagementScope;

final class AdminApiScopes
{
    /** @return array<string, string> */
    public function __invoke(): array
    {
        return ManagementScope::catalog(ApiResource::Admin);
    }
}
