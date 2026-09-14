<?php
declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\Models\User;
use App\Realms\Models\Realm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lock\Server\Authentication\Contracts\CreateUser;

final class CreateNewUser implements CreateUser
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(array $input): User
    {
        // Uniqueness follows the realm: the same address may exist in another
        // one.
        $realm = Realm::current();

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique(User::class)->where('realm_id', $realm->id),
            ],
            'password' => ['required', 'string', 'confirmed'],
        ])->validate();

        return DB::transaction(fn (): User => User::create([
            'realm_id' => $realm->id,
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]));
    }
}
