<?php
declare(strict_types=1);

namespace App\Roles\Ui\Pages;

use App\Admin\Enums\ManagementScope;
use App\Realms\Models\Realm;
use App\Roles\Ui\Tables\RolesTable;
use App\Shared\Ui\Components\ActionBar;
use App\Shared\Ui\Pages\AdminPage;
use Lattice\Core\Attributes\AsPage;
use Lattice\Core\Breadcrumb;
use Lattice\Table\Components\Table;
use Lattice\Ui\Components\Button;
use Lattice\Ui\PageSchema;

#[AsPage(route: '/admin/realms/{realm}/roles', name: 'admin.realms.roles', can: ManagementScope::RolesRead)]
final class RolesPage extends AdminPage
{
    public function title(): string
    {
        return __('roles.heading');
    }

    public function render(PageSchema $schema, Realm $realm): PageSchema
    {
        $schema->breadcrumbs($this->realmBreadcrumbs(
            $realm,
            Breadcrumb::make(__('navigation.roles'), route('admin.realms.roles', ['realm' => $realm->slug], false)),
        ));

        return $schema->schema([
            $this->stack(
                key: 'admin-realm-roles-page',
                heading: __('roles.heading'),
                description: __('roles.description', ['realm' => $realm->name]),
                headerActions: ActionBar::make(primary: [
                    Button::make(__('roles.create.label'))
                        ->can(ManagementScope::RolesWrite)
                        ->href(route('admin.realms.roles.create', ['realm' => $realm->slug], false)),
                ]),
                schema: [
                    Table::use(RolesTable::class),
                ],
            ),
        ]);
    }
}
