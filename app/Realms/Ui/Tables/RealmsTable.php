<?php

declare(strict_types=1);

namespace App\Realms\Ui\Tables;

use App\Admin\Enums\ManagementScope;
use App\Realms\Enums\RealmDomainStatus;
use App\Realms\Models\Realm;
use App\Realms\Ui\Concerns\OpensRealmDeletion;
use App\Realms\Ui\Concerns\PresentsRealmDomainStatus;
use App\Shared\Ui\Components\ActionBar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lattice\Table\Attributes\AsTable;
use Lattice\Table\Columns\BadgeColumn;
use Lattice\Table\Columns\NumberColumn;
use Lattice\Table\Columns\TextColumn;
use Lattice\Table\Components\RowClick;
use Lattice\Table\Sources\Eloquent\EloquentTableDefinition;
use Lattice\Table\TableQuery;

/**
 * @extends EloquentTableDefinition<Realm>
 */
#[AsTable('admin.realms', can: ManagementScope::RealmsRead)]
final class RealmsTable extends EloquentTableDefinition
{
    use OpensRealmDeletion;
    use PresentsRealmDomainStatus;

    /**
     * @return Builder<Realm>
     */
    public function builder(TableQuery $query): Builder
    {
        // The master realm's host lives in APP_URL, not on its row, and it is
        // the host the console is reached on.
        $builder = Realm::query()
            ->select('realms.*')
            ->selectRaw('COALESCE(domain, ?) AS host', [Realm::masterHost()])
            ->selectRaw('CASE WHEN slug = ? THEN ? ELSE domain_status END AS domain_state', [config('lock.master_realm'), RealmDomainStatus::Verified->value])
            ->withCount('users');

        if ($query->sorts === []) {
            $builder->orderBy('name');
        }

        return $builder;
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->label(__('realms.columns.name'))->searchable()->sortable(),
            TextColumn::make('slug')->label(__('realms.columns.slug'))->searchable(),
            TextColumn::make('host')->label(__('realms.columns.domain')),
            BadgeColumn::make('domain_state')
                ->label(__('realms.columns.domain-status'))
                ->options(array_combine(
                    array_column(RealmDomainStatus::cases(), 'value'),
                    array_map(fn (RealmDomainStatus $status): string => __('realms.domain.status.'.$status->value), RealmDomainStatus::cases()),
                ))
                ->colors(array_combine(
                    array_column(RealmDomainStatus::cases(), 'value'),
                    array_map($this->domainStatusColor(...), RealmDomainStatus::cases()),
                )),
            NumberColumn::make('users_count')->label(__('realms.columns.users')),
            TextColumn::make('created_at')->label(__('realms.columns.created-at'))->dateTime()->sortable(),
        ];
    }

    #[\Override]
    public function rowClick(array $row): RowClick
    {
        return RowClick::make()->href(route('admin.realms.settings', ['realm' => $row['slug']], false));
    }

    public function actions(array $row): array
    {
        if (! Gate::allows(ManagementScope::RealmsWrite)) {
            return [];
        }

        return array_filter([ActionBar::menu('admin.realms.row-actions', [$this->deleteRealmTrigger((string) $row['slug'])])]);
    }
}
