<?php

declare(strict_types=1);

namespace App\Realms\Support;

use App\Realms\Models\Realm;
use Lock\Server\Realms\RealmRepository;

final class DatabaseRealmRepository implements RealmRepository
{
    public function find(string $id): ?Realm
    {
        return Realm::query()->where('slug', $id)->first();
    }

    public function findByDomain(string $host): ?Realm
    {
        return Realm::findByHost($host);
    }
}
