<?php
declare(strict_types=1);

namespace App\Clients\Ui\Tables;

use App\Admin\Enums\ManagementScope;
use App\Clients\Data\ClientAttributes;
use App\Clients\Ui\Actions\DeleteClient;
use App\Clients\Ui\Actions\RestoreClient;
use App\Clients\Ui\Actions\RevokeClient;
use App\Clients\Ui\Actions\RotateClientSecret;
use App\Realms\Models\Realm;
use App\Shared\Ui\Components\ActionBar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lattice\Actions\Components\Action;
use Lattice\Table\Attributes\AsTable;
use Lattice\Table\Columns\BadgeColumn;
use Lattice\Table\Columns\TextColumn;
use Lattice\Table\Components\RowClick;
use Lattice\Table\Sources\Eloquent\EloquentTableDefinition;
use Lattice\Table\TableQuery;
use Lock\Server\Clients\Models\Client;
use Lock\Server\Shared\Clients\TokenEndpointAuthMethod;

/**
 * @extends EloquentTableDefinition<Client>
 */
#[AsTable('admin.clients', can: ManagementScope::ClientsRead)]
final class ClientsTable extends EloquentTableDefinition
{
    /**
     * @return Builder<Client>
     */
    public function builder(TableQuery $query): Builder
    {
        $realm = $this->contextModel('realm', Realm::class);

        $builder = Client::query()
            ->inRealm($realm->slug)
            ->select('oidc_clients.*')
            ->selectRaw("CASE WHEN revoked_at IS NOT NULL THEN 'blocked' ELSE 'active' END AS status");

        if ($query->sorts === []) {
            $builder->orderBy('name');
        }

        return $builder;
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->label(__('clients.columns.name'))->searchable()->sortable(),
            TextColumn::make('client_id')->label(__('clients.columns.client-id'))->searchable()->copyable(),
            BadgeColumn::make('token_endpoint_auth_method')
                ->label(__('clients.columns.type'))
                ->options([
                    TokenEndpointAuthMethod::None->value => __('clients.type-badges.public'),
                    TokenEndpointAuthMethod::ClientSecretBasic->value => __('clients.type-badges.confidential'),
                    TokenEndpointAuthMethod::ClientSecretPost->value => __('clients.type-badges.confidential'),
                ])
                ->colors([
                    TokenEndpointAuthMethod::None->value => 'blue',
                    TokenEndpointAuthMethod::ClientSecretBasic->value => 'purple',
                    TokenEndpointAuthMethod::ClientSecretPost->value => 'purple',
                ]),
            TextColumn::make('grant_types')->label(__('clients.columns.grant-types'))->options($this->grantTypeLabels()),
            BadgeColumn::make('status')
                ->label(__('clients.columns.status'))
                ->options([
                    'active' => __('clients.status.active'),
                    'blocked' => __('clients.status.blocked'),
                ])
                ->colors(['active' => 'green', 'blocked' => 'red']),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function grantTypeLabels(): array
    {
        $labels = [];

        foreach ([...ClientAttributes::GRANT_TYPES, 'personal_access'] as $grantType) {
            $labels[$grantType] = __('clients.grant-types.'.str_replace('_', '-', $grantType));
        }

        return $labels;
    }

    #[\Override]
    public function rowClick(array $row): RowClick
    {
        return RowClick::make()->href(route('admin.realms.clients.show', ['realm' => $this->contextString('realm'), 'client' => $row['id']], false));
    }

    public function actions(array $row): array
    {
        if (! Gate::allows(ManagementScope::ClientsWrite)) {
            return [];
        }

        $context = ['client' => $row['id']];
        $confidential = $row['token_endpoint_auth_method'] !== TokenEndpointAuthMethod::None->value;

        return array_filter([ActionBar::menu('admin.clients.row-actions', [
            ...($confidential ? [Action::use(RotateClientSecret::class, $context)] : []),
            ...(($row['revoked_at'] ?? null) !== null
                ? [Action::use(RestoreClient::class, $context)]
                : [Action::use(RevokeClient::class, $context)]),
            Action::use(DeleteClient::class, $context),
        ])]);
    }
}
