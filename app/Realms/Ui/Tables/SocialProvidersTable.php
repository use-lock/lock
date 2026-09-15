<?php
declare(strict_types=1);

namespace App\Realms\Ui\Tables;

use App\Admin\Enums\ManagementScope;
use App\Realms\Enums\SocialProviderDriver;
use App\Realms\Models\Realm;
use App\Realms\Models\RealmSocialProvider;
use App\Realms\Ui\Actions\DeleteSocialProviderAction;
use App\Shared\Ui\Components\ActionBar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lattice\Actions\Components\Action;
use Lattice\Table\Attributes\AsTable;
use Lattice\Table\Columns\BadgeColumn;
use Lattice\Table\Columns\BooleanColumn;
use Lattice\Table\Columns\TextColumn;
use Lattice\Table\Components\RowClick;
use Lattice\Table\Sources\Eloquent\EloquentTableDefinition;
use Lattice\Table\TableQuery;

/**
 * @extends EloquentTableDefinition<RealmSocialProvider>
 */
#[AsTable(self::ID, can: ManagementScope::SocialProvidersRead)]
final class SocialProvidersTable extends EloquentTableDefinition
{
    public const string ID = 'admin.social-providers';

    /**
     * @return Builder<RealmSocialProvider>
     */
    public function builder(TableQuery $query): Builder
    {
        $realm = $this->contextModel('realm', Realm::class);

        $builder = $realm->socialProviders()->getQuery()->select(['id', 'realm_id', 'key', 'driver', 'enabled']);

        if ($query->sorts === []) {
            $builder->orderBy('key');
        }

        return $builder;
    }

    public function columns(): array
    {
        return [
            TextColumn::make('key')->label(__('social-providers.columns.key'))->searchable()->sortable(),
            BadgeColumn::make('driver')
                ->label(__('social-providers.columns.driver'))
                ->options($this->driverLabels())
                ->colors([
                    SocialProviderDriver::Google->value => 'blue',
                    SocialProviderDriver::Apple->value => 'gray',
                    SocialProviderDriver::GitHub->value => 'purple',
                    SocialProviderDriver::Oidc->value => 'green',
                ]),
            BooleanColumn::make('enabled')->label(__('social-providers.columns.enabled'))->sortable(),
        ];
    }

    #[\Override]
    public function rowClick(array $row): RowClick
    {
        return RowClick::make()->href(route('admin.realms.social-providers.show', ['realm' => $this->contextString('realm'), 'socialProvider' => $row['id']], false));
    }

    public function actions(array $row): array
    {
        if (! Gate::allows(ManagementScope::SocialProvidersWrite)) {
            return [];
        }

        return array_filter([ActionBar::menu('admin.social-providers.row-actions', [
            Action::use(DeleteSocialProviderAction::class, ['socialProvider' => $row['id']]),
        ])]);
    }

    /**
     * @return array<string, string>
     */
    private function driverLabels(): array
    {
        $labels = [];

        foreach (SocialProviderDriver::cases() as $driver) {
            $labels[$driver->value] = __('social-providers.drivers.'.$driver->value);
        }

        return $labels;
    }
}
