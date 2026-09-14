<?php
declare(strict_types=1);

use App\Auth\Models\User;
use App\Auth\Ui\Forms\LanguageForm;
use App\Auth\Ui\Forms\TimezoneForm;
use App\Realms\Models\Realm;

use function Tests\Helpers\realmRoute;

it('saves a known locale and rejects an unknown one', function () {
    $user = User::factory()->for(Realm::master())->create(['locale' => 'en']);

    $this->actingAs($user)->submitForm(LanguageForm::class, ['locale' => 'xx'])->assertInvalid(['locale']);
    $this->actingAs($user)->submitForm(LanguageForm::class, ['locale' => 'de'])->assertRedirect(realmRoute($user->realm, 'account'));

    expect($user->refresh()->locale)->toBe('de');
});

it('saves a valid timezone and rejects an invalid one', function () {
    $user = User::factory()->for(Realm::master())->create(['timezone' => null]);

    $this->actingAs($user)->submitForm(TimezoneForm::class, ['timezone' => 'Not/AZone'])->assertInvalid(['timezone']);
    $this->actingAs($user)->submitForm(TimezoneForm::class, ['timezone' => 'Europe/Berlin'])->assertRedirect(realmRoute($user->realm, 'account'));

    expect($user->refresh()->timezone)->toBe('Europe/Berlin');
});
