<?php
declare(strict_types=1);

use App\Realms\Http\Controllers\DomainCheckController;
use App\Shared\Auth\Support\RequestUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    $user = RequestUser::of($request);

    abort_if($user === null, 401);

    return $user->managementScopes()->isNotEmpty()
        ? to_route('admin.realms')
        : to_route('account');
})
    ->middleware(['auth', 'verified'])
    ->name('home');

Route::get('.well-known/lock/domain-check', DomainCheckController::class)->name('realm.domain-check');
