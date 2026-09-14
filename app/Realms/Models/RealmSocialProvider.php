<?php
declare(strict_types=1);

namespace App\Realms\Models;

use App\Realms\Enums\SocialProviderDriver;
use Carbon\CarbonImmutable;
use Database\Factories\RealmSocialProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $realm_id
 * @property string $key
 * @property SocialProviderDriver $driver
 * @property bool $enabled
 * @property array<string, string> $config
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Realm $realm
 *
 * @method static \Database\Factories\RealmSocialProviderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RealmSocialProvider newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RealmSocialProvider newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RealmSocialProvider query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['key', 'driver', 'enabled', 'config'])]
#[Hidden(['config'])]
final class RealmSocialProvider extends Model
{
    /** @use HasFactory<RealmSocialProviderFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Realm, $this>
     */
    public function realm(): BelongsTo
    {
        return $this->belongsTo(Realm::class);
    }

    public function belongsToRealm(Realm $realm): bool
    {
        return $this->realm_id === $realm->id;
    }

    /**
     * The shape use-lock/server's SocialProviderRegistry resolves a driver from.
     *
     * @return array<string, string>
     */
    public function brokeringConfig(): array
    {
        return ['driver' => $this->driver->value, ...$this->config];
    }

    /**
     * The URL the upstream provider has to redirect back to. It lives on the
     * realm's own host, not the console's.
     */
    public function callbackUrl(): string
    {
        return $this->realm->origin().'/auth/social/'.$this->key.'/callback';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver' => SocialProviderDriver::class,
            'enabled' => 'boolean',
            'config' => 'encrypted:array',
        ];
    }
}
