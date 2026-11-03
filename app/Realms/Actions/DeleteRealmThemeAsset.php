<?php

declare(strict_types=1);

namespace App\Realms\Actions;

use App\Realms\Enums\RealmAdminEvent;
use App\Realms\Models\RealmThemeAsset;
use App\Shared\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class DeleteRealmThemeAsset
{
    public function handle(RealmThemeAsset $asset): void
    {
        DB::transaction(function () use ($asset): void {
            $asset->delete();

            Audit::record(RealmAdminEvent::RealmUpdated, $asset->realm, $asset->realm, ['changes' => [
                'theme.files.'.$asset->name => ['old' => $asset->sha256, 'new' => null],
            ]]);
        });

        Storage::disk(RealmThemeAsset::DISK)->delete($asset->path);
    }
}
