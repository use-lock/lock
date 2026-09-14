<?php
declare(strict_types=1);

namespace App\Resources\Ui\Tables;

use App\Admin\Enums\ManagementScope;
use App\Resources\Models\ResourceScope;
use App\Resources\Ui\Actions\DeleteResourceScopeAction;
use App\Resources\Ui\Actions\UpdateResourceScopeAction;
use App\Resources\Ui\Concerns\ResolvesRealmResource;
use App\Shared\Ui\Components\ActionBar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lattice\Actions\Components\Action;
use Lattice\Table\Attributes\AsTable;
use Lattice\Table\Columns\TextColumn;
use Lattice\Table\Sources\Eloquent\EloquentTableDefinition;
use Lattice\Table\TableQuery;

/**
 * @extends EloquentTableDefinition<ResourceScope>
 */
#[AsTable(self::ID, can: ManagementScope::ResourcesRead)]
final class ResourceScopesTable extends EloquentTableDefinition
{
    use ResolvesRealmResource;

    public const string ID = 'admin.resource-scopes';

    /**
     * @return Builder<ResourceScope>
     */
    public function builder(TableQuery $query): Builder
    {
        $builder = $this->resource()->scopes()->getQuery()->select('resource_scopes.*');

        if ($query->sorts === []) {
            $builder->orderBy('value');
        }

        return $builder;
    }

    public function columns(): array
    {
        return [
            TextColumn::make('value')->label(__('resources.scopes.columns.value'))->searchable()->sortable()->copyable(),
            TextColumn::make('description')->label(__('resources.scopes.columns.description'))->searchable(),
        ];
    }

    public function actions(array $row): array
    {
        if (! Gate::allows(ManagementScope::ResourcesWrite)) {
            return [];
        }

        $context = ['resourceScope' => $row['id']];

        return array_filter([ActionBar::menu('admin.resource-scopes.row-actions', [
            Action::use(UpdateResourceScopeAction::class, $context),
            Action::use(DeleteResourceScopeAction::class, $context),
        ])]);
    }
}
