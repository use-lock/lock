<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Auth\Models\User;
use App\Auth\Ui\Tables\UsersTable;
use App\Clients\Ui\Tables\ClientsTable;
use App\Realms\Models\Realm;
use Lattice\Support\Testing\ComponentNode;
use Lattice\Support\Testing\TableRow;

use function Tests\Helpers\globalAdmin;
use function Tests\Helpers\headerSlot;
use function Tests\Helpers\realmClient;

/**
 * Both only send a mail the recipient can ignore; everything else changes state
 * and needs the confirmation dialog in front of it.
 */
const HARMLESS_ACTIONS = [
    'admin.users.resend-verification',
    'admin.users.send-password-reset',
];

beforeEach(function () {
    $this->realm = Realm::factory()->create(['slug' => 'acme']);
    $this->target = User::factory()->for($this->realm)->create();
    $this->client = realmClient($this->realm, 'Portal');
    $this->actingAs(globalAdmin());
});

test('every console action that changes something confirms first', function () {
    $surfaces = collect([
        headerSlot($this->get("/admin/realms/acme/users/{$this->target->id}")->assertOk()),
        headerSlot($this->get("/admin/realms/acme/clients/{$this->client->id}")->assertOk()),
        ...array_map(fn (TableRow $row): ComponentNode => $row->actions(), $this->loadTable(UsersTable::class, context: ['realm' => 'acme'])->assertOk()->rows()),
        ...array_map(fn (TableRow $row): ComponentNode => $row->actions(), $this->loadTable(ClientsTable::class, context: ['realm' => 'acme'])->assertOk()->rows()),
    ])->filter(fn (mixed $node): bool => $node instanceof ComponentNode);

    $unconfirmed = $surfaces
        ->flatMap(fn (ComponentNode $node): array => $node->findAll(fn (ComponentNode $item): bool => $item->type() === 'action'))
        ->reject(fn (ComponentNode $node): bool => in_array((string) $node->id(), HARMLESS_ACTIONS, true))
        ->filter(fn (ComponentNode $node): bool => $node->prop('confirmation') === null)
        ->map(fn (ComponentNode $node): string => (string) $node->id())
        ->unique()
        ->values()
        ->all();

    expect($surfaces)->toHaveCount(4)
        ->and($unconfirmed)->toBeEmpty();
});
