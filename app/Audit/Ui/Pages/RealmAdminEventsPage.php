<?php
declare(strict_types=1);

namespace App\Audit\Ui\Pages;

use App\Admin\Enums\ManagementScope;
use App\Audit\Ui\Tables\RealmAdminEventsTable;
use App\Realms\Models\Realm;
use App\Shared\Ui\Pages\AdminPage;
use Lattice\Core\Attributes\AsPage;
use Lattice\Core\Breadcrumb;
use Lattice\Table\Components\Table;
use Lattice\Ui\PageSchema;

#[AsPage(route: '/admin/realms/{realm}/admin-events', name: 'admin.realms.admin-events', can: ManagementScope::AdminEventsRead)]
final class RealmAdminEventsPage extends AdminPage
{
    public function title(): string
    {
        return __('audit.pages.realm-admin.heading');
    }

    public function render(PageSchema $schema, Realm $realm): PageSchema
    {
        $schema->breadcrumbs($this->realmBreadcrumbs(
            $realm,
            Breadcrumb::make(__('navigation.admin-events'), route('admin.realms.admin-events', ['realm' => $realm->slug], false)),
        ));

        return $schema->schema([
            $this->stack(
                key: 'admin-realm-admin-events-page',
                heading: __('audit.pages.realm-admin.heading'),
                description: __('audit.pages.realm-admin.description', ['realm' => $realm->name]),
                schema: [
                    Table::use(RealmAdminEventsTable::class),
                ],
            ),
        ]);
    }
}
