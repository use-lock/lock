<?php
declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\Models\User;
use App\Realms\Models\Realm;
use App\Shared\Tenants\Contracts\ProvisionsPersonalTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lock\Server\Authentication\Contracts\CreateUser;

final readonly class CreateNewUser implements CreateUser
{
    public function __construct(private ProvisionsPersonalTenant $personalTenant) {}

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

        return DB::transaction(function () use ($realm, $input): User {
            $user = User::create([
                'realm_id' => $realm->id,
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $this->personalTenant->forNewUser($user);

            return $user;
        });
    }
}
