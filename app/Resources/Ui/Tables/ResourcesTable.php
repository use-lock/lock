<?php
declare(strict_types=1);

namespace App\Resources\Ui\Tables;

use App\Admin\Enums\ManagementScope;
use App\Realms\Models\Realm;
use App\Resources\Ui\Actions\DeleteResourceAction;
use App\Resources\Ui\Actions\UpdateResourceAction;
use App\Shared\Ui\Components\ActionBar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lattice\Actions\Components\Action;
use Lattice\Table\Attributes\AsTable;
use Lattice\Table\Columns\NumberColumn;
use Lattice\Table\Columns\TextColumn;
use Lattice\Table\Components\RowClick;
use Lattice\Table\Sources\Eloquent\EloquentTableDefinition;
use Lattice\Table\TableQuery;

/**
 * @extends EloquentTableDefinition<\App\Resources\Models\Resource>
 */
#[AsTable(self::ID, can: ManagementScope::ResourcesRead)]
final class ResourcesTable extends EloquentTableDefinition
{
    public const string ID = 'admin.resources';

    /**
     * @return Builder<\App\Resources\Models\Resource>
     */
    public function builder(TableQuery $query): Builder
    {
        $realm = $this->contextModel('realm', Realm::class);

        $builder = $realm->realmResources()->getQuery()
            ->select('resources.*')
            ->withCount('scopes');

        if ($query->sorts === []) {
            $builder->orderBy('identifier');
        }

        return $builder;
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->label(__('resources.columns.name'))->searchable()->sortable(),
            TextColumn::make('identifier')->label(__('resources.columns.identifier'))->searchable()->sortable()->copyable(),
            NumberColumn::make('scopes_count')->label(__('resources.columns.scopes'))->decimals(0),
        ];
    }

    #[\Override]
    public function rowClick(array $row): RowClick
    {
        return RowClick::make()->href(route('admin.realms.resources.show', ['realm' => $this->contextString('realm'), 'resource' => $row['id']], false));
    }

    public function actions(array $row): array
    {
        if (! Gate::allows(ManagementScope::ResourcesWrite)) {
            return [];
        }

        $context = ['resource' => $row['id']];

        return array_filter([ActionBar::menu('admin.resources.row-actions', [
            Action::use(UpdateResourceAction::class, $context),
            Action::use(DeleteResourceAction::class, $context),
        ])]);
    }
}
