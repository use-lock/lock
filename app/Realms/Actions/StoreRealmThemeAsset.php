<?php

declare(strict_types=1);

namespace App\Realms\Actions;

use App\Realms\Enums\RealmAdminEvent;
use App\Realms\Models\Realm;
use App\Realms\Models\RealmThemeAsset;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class StoreRealmThemeAsset
{
    /**
     * Idempotent: a Pulumi program uploads on every change of its inputs, and
     * the same bytes under the same name change nothing.
     *
     * @throws ValidationException
     */
    public function handle(Realm $realm, string $name, string $contents): RealmThemeAsset
    {
        $this->validate($realm, $name, $contents);

        $sha256 = hash('sha256', $contents);
        $existing = $realm->themeAssets()->where('name', $name)->first();

        if ($existing?->sha256 === $sha256) {
            return $existing;
        }

        $disk = Storage::disk(RealmThemeAsset::DISK);
        $path = "realm-themes/{$realm->id}/{$sha256}-{$name}";
        $disk->put($path, $contents);

        try {
            $asset = DB::transaction(function () use ($realm, $name, $contents, $path, $sha256, $existing): RealmThemeAsset {
                $asset = $realm->themeAssets()->updateOrCreate(['name' => $name], [
                    'path' => $path,
                    'mime_type' => RealmThemeAsset::TYPES[pathinfo($name, PATHINFO_EXTENSION)],
                    'size' => strlen($contents),
                    'sha256' => $sha256,
                ]);

                Audit::record(RealmAdminEvent::RealmUpdated, $realm, $realm, ['changes' => [
                    'theme.files.'.$name => ['old' => $existing?->sha256, 'new' => $sha256],
                ]]);

                return $asset;
            });
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }

        if ($existing !== null) {
            $disk->delete($existing->path);
        }

        return $asset;
    }

    /**
     * @throws ValidationException
     */
    private function validate(Realm $realm, string $name, string $contents): void
    {
        $error = match (true) {
            $realm->isMaster() => __('realms.theme.errors.master'),
            ! RealmThemeAsset::isValidName($name) => __('realms.theme.assets.errors.name'),
            strlen($contents) > RealmThemeAsset::MAX_BYTES => __('realms.theme.assets.errors.size'),
            ! $this->holdsWhatItsNameSays(pathinfo($name, PATHINFO_EXTENSION), $contents) => __('realms.theme.assets.errors.content'),
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['file' => [$error]]);
        }
    }

    /**
     * By signature rather than by what a client or a sniffer calls the file, so
     * no HTML ends up behind an image's name.
     */
    private function holdsWhatItsNameSays(string $extension, string $contents): bool
    {
        return match ($extension) {
            'png' => str_starts_with($contents, "\x89PNG\r\n\x1a\n"),
            'jpg', 'jpeg' => str_starts_with($contents, "\xFF\xD8\xFF"),
            'webp' => str_starts_with($contents, 'RIFF') && substr($contents, 8, 4) === 'WEBP',
            'woff2' => str_starts_with($contents, 'wOF2'),
            'svg' => preg_match('/\A(?:\xEF\xBB\xBF)?\s*(?:<\?xml[^>]*\?>\s*)?(?:<!--.*?-->\s*)*(?:<!DOCTYPE[^>]*>\s*)?<svg[\s>]/s', $contents) === 1,
            default => false,
        };
    }
}
