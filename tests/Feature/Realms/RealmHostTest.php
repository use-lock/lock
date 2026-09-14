<?php

declare(strict_types=1);

use App\Auth\Models\User;
use App\Auth\Ui\Actions\ResendUserVerification;
use App\Auth\Ui\Actions\SendUserPasswordReset;
use App\Realms\Models\Realm;
use App\Realms\Support\DomainCheckToken;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;

use function Tests\Helpers\globalAdmin;

beforeEach(function () {
    $this->realm = Realm::factory()->create(['slug' => 'acme', 'domain' => 'auth.acme.test']);
});

it('serves a realm its sign-in and discovery from its own host and none of the console', function () {
    $this->get('http://auth.acme.test/auth/login')->assertOk();
    $this->getJson('http://auth.acme.test/.well-known/openid-configuration')
        ->assertOk()
        ->assertJsonPath('issuer', 'http://auth.acme.test');

    $this->actingAs(globalAdmin())->get('http://auth.acme.test/admin/realms')->assertNotFound();
    $this->get('http://auth.acme.test/login')->assertNotFound();
});

it('sends the root of a realm host to the realm account', function () {
    $this->get('http://auth.acme.test/')->assertRedirect('http://auth.acme.test/account');
});

it('answers a host no realm is served from with nothing but the health and domain checks', function () {
    $this->get('http://intruder.test/auth/login')->assertNotFound();
    $this->get('http://intruder.test/auth/forgot-password')->assertNotFound();

    $this->get('http://intruder.test/up')->assertOk();
    $this->get('http://intruder.test/.well-known/lock/domain-check')
        ->assertOk()
        ->assertContent(DomainCheckToken::for('intruder.test'));
});

it('points the mails a realm user gets from the console at their realm host', function () {
    $sent = [];
    Event::listen(MessageSent::class, function (MessageSent $event) use (&$sent): void {
        $sent[] = (string) $event->message->getHtmlBody();
    });
    $user = User::factory()->for($this->realm)->unverified()->create();

    $this->actingAs(globalAdmin());
    $this->callAction(SendUserPasswordReset::class, [], ['user' => $user->id])->assertOk();
    $this->callAction(ResendUserVerification::class, [], ['user' => $user->id])->assertOk();

    preg_match_all('#https?://[^"\'\s<>]+/auth/(?:reset-password|email/verify)/[^"\'\s<>]+#', implode("\n", $sent), $links);

    expect(array_values(array_unique($links[0])))->toHaveCount(2)
        ->each->toStartWith('http://auth.acme.test/auth/');
});
