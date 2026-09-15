<?php
declare(strict_types=1);

namespace App\Admin\Http\Api;

use App\Admin\Enums\ApiResource;
use App\Admin\Enums\ManagementScope;

/**
 * The scope map of the document's oauth2 flows. A cached config file cannot
 * hold a closure, so Spectacular resolves this class instead.
 */
final class ApiScopes
{
    /** @return array<string, string> */
    public function __invoke(): array
    {
        return ManagementScope::catalog(ApiResource::Management);
    }
}
