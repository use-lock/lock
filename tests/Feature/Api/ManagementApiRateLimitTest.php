<?php
declare(strict_types=1);

use App\Admin\Enums\ManagementScope;

use function Tests\Helpers\managementApiToken;

test('the management API budgets each principal separately', function () {
    $first = managementApiToken($this, ManagementScope::RealmsRead);
    $second = managementApiToken($this, ManagementScope::RealmsRead);

    $this->withToken($first)->getJson('/api/v1/realms')->assertOk()
        ->assertHeader('X-RateLimit-Limit', '300')
        ->assertHeader('X-RateLimit-Remaining', '299');

    auth()->forgetGuards();
    $this->withToken($first)->getJson('/api/v1/realms')->assertHeader('X-RateLimit-Remaining', '298');

    auth()->forgetGuards();
    $this->withToken($second)->getJson('/api/v1/realms')->assertHeader('X-RateLimit-Remaining', '299');
});
