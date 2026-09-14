<?php

declare(strict_types=1);

use App\Auth\Models\User;
use App\Realms\Models\Realm;
use Lattice\Support\Testing\ComponentNode;

use function Tests\Helpers\assumeNotNull;
use function Tests\Helpers\identitySessionFor;
use function Tests\Helpers\latticeSchema;
use function Tests\Helpers\realmRoute;
use function Tests\Helpers\realmUrl;

it('does not let a session from one realm open another realm account', function () {
    $acme = Realm::factory()->create(['slug' => 'acme']);
    Realm::factory()->create(['slug' => 'globex']);
    $user = User::factory()->for($acme)->create();

    $this->withSession(identitySessionFor($user))
        ->get(realmRoute('globex', 'account'))
        ->assertRedirect(realmRoute('globex', 'identity.login'));

    auth()->forgetGuards();

    $this->withSession(identitySessionFor($user))
        ->get(realmRoute('acme', 'account'))
        ->assertOk();
});

it('mints its form endpoints below the account and accepts the identity session there', function () {
    $realm = Realm::factory()->create(['slug' => 'acme']);
    $user = User::factory()->for($realm)->create(['name' => 'Old Name']);

    $page = $this->actingAs($user, 'identity')->get(realmRoute('acme', 'account'))->assertOk();

    $nameForm = latticeSchema($page)->find(fn (ComponentNode $node): bool => $node->type() === 'form'
        && $node->find(fn (ComponentNode $field): bool => $field->prop('name') === 'name') instanceof ComponentNode);
    assumeNotNull($nameForm, 'The account page renders the name form.');

    $action = $nameForm->prop('action');
    $sessionCookie = (string) config('session.cookie');
    $sessionId = $page->getCookie($sessionCookie)?->getValue();
    assumeNotNull($sessionId, 'The account page starts the realm session.');

    expect($action)->toBeString()->toStartWith('/account/lattice/forms/');

    $this->actingAs($user, 'identity')
        ->withCookie($sessionCookie, $sessionId)
        ->patch(realmUrl($realm, (string) $action), ['name' => 'New Name'], [
            'X-Inertia' => 'true',
            'X-Lattice-Ref' => (string) $nameForm->prop('ref'),
        ])
        ->assertRedirect(realmRoute('acme', 'account'));

    expect($user->refresh()->name)->toBe('New Name');
});

it('mints the two-factor setup form below the account as well', function () {
    $realm = Realm::factory()->create(['slug' => 'acme']);
    $user = User::factory()->for($realm)->create();

    $page = $this->actingAs($user, 'identity')
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(realmRoute('acme', 'identity.two-factor.setup'))
        ->assertOk();

    $form = latticeSchema($page)->find(fn (ComponentNode $node): bool => $node->type() === 'form');
    assumeNotNull($form, 'The setup page renders the setup form.');

    expect($form->prop('action'))->toBeString()->toStartWith('/account/lattice/forms/');
});

it('sends a signed-in identity from the realm sign-in to its account', function () {
    $realm = Realm::factory()->create(['slug' => 'acme']);
    $user = User::factory()->for($realm)->create();

    $this->actingAs($user, 'identity')
        ->get(realmRoute('acme', 'identity.login'))
        ->assertRedirect(realmRoute('acme', 'account'));
});
