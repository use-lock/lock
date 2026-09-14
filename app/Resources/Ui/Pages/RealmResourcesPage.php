<?php
declare(strict_types=1);

namespace App\Resources\Ui\Pages;

use App\Admin\Enums\ManagementScope;
use App\Realms\Models\Realm;
use App\Resources\Ui\Actions\CreateResourceAction;
use App\Resources\Ui\Tables\ResourcesTable;
use App\Shared\Ui\Components\ActionBar;
use App\Shared\Ui\Pages\AdminPage;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsPage;
use Lattice\Core\Breadcrumb;
use Lattice\Table\Components\Table;
use Lattice\Ui\PageSchema;

#[AsPage(route: '/admin/realms/{realm}/resources', name: 'admin.realms.resources', can: ManagementScope::ResourcesRead)]
final class RealmResourcesPage extends AdminPage
{
    public function title(): string
    {
        return __('resources.heading');
    }

    public function render(PageSchema $schema, Realm $realm): PageSchema
    {
        $schema->breadcrumbs($this->realmBreadcrumbs(
            $realm,
            Breadcrumb::make(__('navigation.resources'), route('admin.realms.resources', ['realm' => $realm->slug], false)),
        ));

        return $schema->schema([
            $this->stack(
                key: 'admin-realm-resources-page',
                heading: __('resources.heading'),
                description: __('resources.description', ['realm' => $realm->name]),
                headerActions: ActionBar::make(primary: [
                    Action::use(CreateResourceAction::class),
                ]),
                schema: [
                    Table::use(ResourcesTable::class),
                ],
            ),
        ]);
    }
}
