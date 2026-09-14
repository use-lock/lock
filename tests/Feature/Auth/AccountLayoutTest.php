<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Admin\ManagementRoles;
use App\Auth\Models\User;
use App\Realms\Models\Realm;
use Lattice\Support\Testing\ComponentNode;

use function Tests\Helpers\grantGlobalRole;
use function Tests\Helpers\latticeSchema;
use function Tests\Helpers\menuItemHrefs;
use function Tests\Helpers\realmRoute;
use function Tests\Helpers\runningTestCase;

function accountLayoutSchema(User $user): ComponentNode
{
    $response = runningTestCase()->actingAs($user, 'identity')->get(realmRoute($user->realm, 'account'))->assertOk();

    return latticeSchema($response, 'props.lattice.layout.schema');
}

/**
 * @return array<int, mixed>
 */
function accountUserMenuHrefs(User $user): array
{
    return menuItemHrefs(accountLayoutSchema($user)->firstOfTypeOrFail('dropdown', 'user-menu'));
}

it('offers the realm end-session as logout and the console only to admins', function () {

    $user = User::factory()->for(Realm::master())->create();
    $hrefsBefore = accountUserMenuHrefs($user);

    grantGlobalRole($user, ManagementRoles::SUPPORT);
    $hrefsAfter = accountUserMenuHrefs($user->refresh());

    expect($hrefsBefore)->toBe(['/oauth/logout'])
        ->and($hrefsAfter)->toBe(['/admin/realms', '/oauth/logout']);
});
