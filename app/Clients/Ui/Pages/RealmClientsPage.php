<?php
declare(strict_types=1);

namespace App\Clients\Ui\Pages;

use App\Admin\Enums\ManagementScope;
use App\Clients\Ui\Tables\ClientsTable;
use App\Realms\Models\Realm;
use App\Shared\Ui\Components\ActionBar;
use App\Shared\Ui\Pages\AdminPage;
use Lattice\Core\Attributes\AsPage;
use Lattice\Core\Breadcrumb;
use Lattice\Facades\Effects;
use Lattice\Table\Components\Table;
use Lattice\Ui\Components\Button;
use Lattice\Ui\PageSchema;

#[AsPage(route: '/admin/realms/{realm}/clients', name: 'admin.realms.clients', can: ManagementScope::ClientsRead)]
final class RealmClientsPage extends AdminPage
{
    public function title(): string
    {
        return __('clients.heading');
    }

    public function render(PageSchema $schema, Realm $realm): PageSchema
    {
        $schema->breadcrumbs($this->realmBreadcrumbs(
            $realm,
            Breadcrumb::make(__('navigation.clients'), route('admin.realms.clients', ['realm' => $realm->slug], false)),
        ));

        return $schema->schema([
            $this->stack(
                key: 'admin-realm-clients-page',
                heading: __('clients.heading'),
                description: __('clients.description', ['realm' => $realm->name]),
                headerActions: ActionBar::make(primary: [
                    Button::make(__('clients.create.submit'))
                        ->effects(Effects::redirect(route('admin.realms.clients.create', ['realm' => $realm->slug], false))),
                ]),
                schema: [
                    Table::use(ClientsTable::class),
                ],
            ),
        ]);
    }
}
