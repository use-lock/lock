<?php

declare(strict_types=1);

namespace App\Realms\Ui\Tables;

use App\Admin\Enums\ManagementScope;
use App\Realms\Models\Realm;
use App\Realms\Models\RealmThemeAsset;
use App\Realms\Ui\Actions\DeleteRealmThemeAssetAction;
use App\Shared\Ui\Components\ActionBar;
use Illuminate\Database\Eloquent\Builder;
use Lattice\Actions\Components\Action;
use Lattice\Table\Attributes\AsTable;
use Lattice\Table\Columns\NumberColumn;
use Lattice\Table\Columns\TextColumn;
use Lattice\Table\Sources\Eloquent\EloquentTableDefinition;
use Lattice\Table\TableQuery;

/**
 * @extends EloquentTableDefinition<RealmThemeAsset>
 */
#[AsTable(self::ID, can: ManagementScope::RealmsWrite)]
final class RealmThemeAssetsTable extends EloquentTableDefinition
{
    public const string ID = 'admin.realm-theme-assets';

    /**
     * @return Builder<RealmThemeAsset>
     */
    public function builder(TableQuery $query): Builder
    {
        $builder = $this->contextModel('realm', Realm::class)->themeAssets()->getQuery()->select(['id', 'realm_id', 'name', 'mime_type', 'size']);

        if ($query->sorts === []) {
            $builder->orderBy('name');
        }

        return $builder;
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->label(__('realms.theme.assets.columns.name'))->searchable()->sortable(),
            TextColumn::make('mime_type')->label(__('realms.theme.assets.columns.type')),
            NumberColumn::make('size')->label(__('realms.theme.assets.columns.size'))->sortable(),
        ];
    }

    public function actions(array $row): array
    {
        return array_filter([ActionBar::menu('admin.realm-theme-assets.row-actions', [
            Action::use(DeleteRealmThemeAssetAction::class, ['themeAsset' => $row['id']]),
        ])]);
    }
}
