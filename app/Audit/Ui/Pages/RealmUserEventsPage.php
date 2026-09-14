<?php
declare(strict_types=1);

namespace App\Audit\Ui\Pages;

use App\Admin\Enums\ManagementScope;
use App\Audit\Ui\Tables\RealmUserEventsTable;
use App\Realms\Models\Realm;
use App\Shared\Ui\Pages\AdminPage;
use Lattice\Core\Attributes\AsPage;
use Lattice\Core\Breadcrumb;
use Lattice\Table\Components\Table;
use Lattice\Ui\PageSchema;

#[AsPage(route: '/admin/realms/{realm}/user-events', name: 'admin.realms.user-events', can: ManagementScope::UserEventsRead)]
final class RealmUserEventsPage extends AdminPage
{
    public function title(): string
    {
        return __('audit.pages.realm-user.heading');
    }

    public function render(PageSchema $schema, Realm $realm): PageSchema
    {
        $schema->breadcrumbs($this->realmBreadcrumbs(
            $realm,
            Breadcrumb::make(__('navigation.user-events'), route('admin.realms.user-events', ['realm' => $realm->slug], false)),
        ));

        return $schema->schema([
            $this->stack(
                key: 'admin-realm-user-events-page',
                heading: __('audit.pages.realm-user.heading'),
                description: __('audit.pages.realm-user.description', ['realm' => $realm->name]),
                schema: [
                    Table::use(RealmUserEventsTable::class),
                ],
            ),
        ]);
    }
}
