<?php

declare(strict_types=1);

namespace App\Realms\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $realm_id
 * @property string $name
 * @property string $path
 * @property string $mime_type
 * @property int $size
 * @property string $sha256
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Realm $realm
 */
#[Fillable(['name', 'path', 'mime_type', 'size', 'sha256'])]
final class RealmThemeAsset extends Model
{
    use HasUuids;

    /** The volume every Lock container shares; the image links no public disk. */
    public const string DISK = 'local';

    public const int MAX_BYTES = 5_242_880;

    /**
     * The name decides the type a file is served with, so the served type is
     * never one a client chose.
     */
    public const array TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'woff2' => 'font/woff2',
    ];

    public const string NAME_PATTERN = '[a-z0-9][a-z0-9._-]{0,122}\.(?:png|jpe?g|webp|svg|woff2)';

    public static function isValidName(string $name): bool
    {
        return preg_match('/\A'.self::NAME_PATTERN.'\z/', $name) === 1 && ! str_contains($name, '..');
    }

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

    public function version(): string
    {
        return substr($this->sha256, 0, 16);
    }

    /**
     * Relative, because the file is only served on its realm's host.
     */
    public function path(): string
    {
        return route('realm.theme-asset', ['name' => $this->name, 'v' => $this->version()], absolute: false);
    }

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }
}
