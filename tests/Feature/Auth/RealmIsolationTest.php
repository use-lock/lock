<?php
declare(strict_types=1);

/**
 * The isolation use-lock/server cannot provide: it does not own the user model, so
 * the realm constraint on credential lookup is the application's to enforce.
 */

use App\Auth\Models\User;
use App\Realms\Models\Realm;

beforeEach(function () {
    $this->acme = Realm::factory()->create(['slug' => 'acme']);
    $this->globex = Realm::factory()->create(['slug' => 'globex']);

    $this->inAcme = User::factory()->for($this->acme)->create(['email' => 'chris@example.com']);
    $this->inGlobex = User::factory()->for($this->globex)->create(['email' => 'chris@example.com']);
});

it('signs the address in as the user of the realm it was offered to', function () {
    $signedInWithin = fn (Realm $realm): ?string => $realm->runAsCurrent(function (): ?string {
        auth()->forgetGuards();
        auth()->guard('identity')->attempt(['email' => 'chris@example.com', 'password' => 'password']);

        $user = auth()->guard('identity')->user();

        return $user instanceof User ? $user->id : null;
    });

    expect($signedInWithin($this->acme))->toBe($this->inAcme->id)
        ->and($signedInWithin($this->globex))->toBe($this->inGlobex->id);
});
