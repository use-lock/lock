<?php
declare(strict_types=1);

namespace App\Shared\Auth\Contracts;

use App\Auth\Models\User;
use App\Realms\Models\Realm;
use SensitiveParameter;

interface CreatesRealmUser
{
    public function handle(
        Realm $realm,
        string $name,
        string $email,
        #[SensitiveParameter] ?string $password,
        bool $verified,
    ): User;
}
