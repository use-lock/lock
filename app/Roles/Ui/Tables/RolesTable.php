<?php
declare(strict_types=1);

namespace App\Roles\Ui\Tables;

use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementRoles;
use App\Realms\Models\Realm;
use App\Roles\Models\Role;
use App\Roles\Ui\Actions\DeleteRoleAction;
use App\Roles\Ui\Actions\UpdateRoleAction;
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
 * @extends EloquentTableDefinition<Role>
 */
#[AsTable(self::ID, can: ManagementScope::RolesRead)]
final class RolesTable extends EloquentTableDefinition
{
    public const string ID = 'admin.realm-roles';

    /**
     * @return Builder<Role>
     */
    public function builder(TableQuery $query): Builder
    {
        $realm = $this->contextModel('realm', Realm::class);

        $builder = $realm->roles()->getQuery()
            ->select('roles.*')
            ->withCount('users');

        if ($query->sorts === []) {
            $builder->orderBy('name');
        }

        return $builder;
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->label(__('roles.columns.name'))->searchable()->sortable(),
            TextColumn::make('description')->label(__('roles.columns.description'))->searchable(),
            NumberColumn::make('users_count')->label(__('roles.columns.users'))->decimals(0),
        ];
    }

    #[\Override]
    public function rowClick(array $row): RowClick
    {
        return RowClick::make()->href(route('admin.realms.roles.show', ['realm' => $this->contextString('realm'), 'role' => $row['id']], false));
    }

    public function actions(array $row): array
    {
        if (! Gate::allows(ManagementScope::RolesWrite)) {
            return [];
        }

        if ($this->contextModel('realm', Realm::class)->isMaster() && in_array($row['name'], ManagementRoles::names(), true)) {
            return [];
        }

        $context = ['role' => $row['id']];

        return array_filter([ActionBar::menu('admin.roles.row-actions', [
            Action::use(UpdateRoleAction::class, $context),
            Action::use(DeleteRoleAction::class, $context),
        ])]);
    }
}
