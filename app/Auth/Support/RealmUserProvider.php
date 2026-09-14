<?php
declare(strict_types=1);

namespace App\Auth\Support;

use App\Realms\Models\Realm;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Identity is realm-local: without the realm constraint a password valid in one
 * realm authenticates in all of them, and a session minted in one resolves a
 * user in another. The block constraint is unconditional, so a blocked user
 * fails every lookup: password, session, remember cookie and bearer token.
 */
final class RealmUserProvider extends EloquentUserProvider
{
    /**
     * The single seam `retrieveById`, `retrieveByToken` and
     * `retrieveByCredentials` all build their query from.
     *
     * @param  Model|null  $model
     * @return Builder<Model>
     */
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)
            ->whereNull('blocked_at')
            ->where('realm_id', Realm::current()->id);
    }
}
