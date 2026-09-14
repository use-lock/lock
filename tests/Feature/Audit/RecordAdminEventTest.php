<?php
declare(strict_types=1);

use App\Audit\Models\AdminEvent;
use App\Auth\Enums\UserAdminEvent;
use App\Auth\Models\User;
use App\Clients\Enums\ClientAdminEvent;
use App\Realms\Enums\RealmAdminEvent;
use App\Realms\Models\Realm;
use App\Realms\Models\RealmSocialProvider;
use App\Resources\Enums\ResourceAdminEvent;
use App\Resources\Models\ResourceScope;
use App\Roles\Enums\RoleAdminEvent;
use App\Roles\Models\Role;
use App\Shared\Audit\Audit;
use App\Shared\Audit\Contracts\AdminEventType;
use App\Shared\Audit\Enums\AdminEventCategory;
use Illuminate\Http\Request;
use Lock\Server\Clients\Models\Client;

test('it records the actor, the subject, the realm and the request provenance', function () {
    $user = User::factory()->create();
    $realm = Realm::factory()->create();
    $this->actingAs($user);

    $this->app->instance('request', Request::create('/admin', server: [
        'REMOTE_ADDR' => '203.0.113.7',
        'HTTP_USER_AGENT' => 'Lock/1.0',
    ]));

    Audit::record(RealmAdminEvent::RealmUpdated, $realm, $realm, ['changes' => ['name' => ['old' => 'Old', 'new' => 'New']]]);

    $event = AdminEvent::query()->sole();

    expect($event->type)->toBe('realm.updated')
        ->and($event->category)->toBe(AdminEventCategory::Realm->value)
        ->and($event->realm_id)->toBe($realm->id)
        ->and($event->user_id)->toBe($user->id)
        ->and($event->subject_id)->toBe($realm->id)
        ->and($event->subject_type)->toBe($realm->getMorphClass())
        ->and($event->ip)->toBe('203.0.113.7')
        ->and($event->user_agent)->toBe('Lock/1.0')
        ->and($event->context)->toBe(['changes' => ['name' => ['old' => 'Old', 'new' => 'New']]]);
});

test('it translates the type at read time, in the viewer locale', function () {
    $realm = Realm::factory()->create();

    Audit::record(RealmAdminEvent::RealmUpdated, $realm, $realm);

    $event = AdminEvent::query()->sole();
    expect($event->type_label)->toBe('Realm updated');

    app()->setLocale('de');
    expect($event->type_label)->toBe('Realm aktualisiert');
});

test('it names the acting administrator, and nobody when nothing acted', function () {
    $realm = Realm::factory()->create();
    Audit::record(RealmAdminEvent::RealmUpdated, $realm, $realm);

    $admin = User::factory()->create();
    $this->actingAs($admin);
    Audit::record(RealmAdminEvent::RealmUpdated, $realm, $realm);

    expect(AdminEvent::query()->whereNull('user_id')->sole()->actor_name)->toBe('—')
        ->and(AdminEvent::query()->where('user_id', $admin->id)->sole()->actor_name)->toBe($admin->name);
});

test('nothing is recorded inside withoutRecording', function () {
    $realm = Realm::factory()->create();

    Audit::withoutRecording(fn () => Audit::record(RealmAdminEvent::RealmUpdated, $realm, $realm));

    expect(AdminEvent::query()->count())->toBe(0);
});

test('audit subjects retain stable aliases and resolve their owning models', function (Closure $create, AdminEventType $type, string $alias) {
    $subject = $create();
    Audit::record($type, $subject);

    $event = AdminEvent::query()->sole();

    expect($event->subject_type)->toBe($alias)
        ->and($event->subject?->is($subject))->toBeTrue();
})->with([
    'user' => [fn () => User::factory()->create(), UserAdminEvent::UserUpdated, 'user'],
    'realm' => [fn () => Realm::factory()->create(), RealmAdminEvent::RealmUpdated, 'realm'],
    'social provider' => [fn () => RealmSocialProvider::factory()->create(), RealmAdminEvent::SocialProviderUpdated, 'social-provider'],
    'client' => [fn () => Client::factory()->create(), ClientAdminEvent::ClientUpdated, 'client'],
    'role' => [fn () => Role::factory()->create(), RoleAdminEvent::RoleUpdated, 'role'],
    'resource' => [fn () => App\Resources\Models\Resource::factory()->create(), ResourceAdminEvent::ResourceUpdated, 'resource'],
    'resource scope' => [fn () => ResourceScope::factory()->create(), ResourceAdminEvent::ScopeUpdated, 'resource-scope'],
]);
