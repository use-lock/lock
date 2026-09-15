<?php
declare(strict_types=1);

namespace App\Admin\Enums;

enum ApiResource: string
{
    case Admin = 'admin-api';

    case Management = 'api';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin API',
            self::Management => 'Management API',
        };
    }

    /** @return list<ManagementScope> */
    public function scopes(): array
    {
        return array_values(array_filter(ManagementScope::cases(), fn (ManagementScope $scope): bool => $scope->apiResource() === $this));
    }

    /** @return list<string> */
    public function values(): array
    {
        return array_column($this->scopes(), 'value');
    }

    /** @return array<string, string> */
    public function catalog(): array
    {
        return ManagementScope::catalog($this);
    }
}
