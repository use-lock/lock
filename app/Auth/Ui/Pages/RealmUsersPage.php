<?php

declare(strict_types=1);

namespace App\Auth\Ui\Pages;

use App\Admin\Enums\ManagementScope;
use App\Auth\Ui\Tables\UsersTable;
use App\Realms\Models\Realm;
use App\Shared\Ui\Components\ActionBar;
use App\Shared\Ui\Pages\AdminPage;
use Lattice\Core\Attributes\AsPage;
use Lattice\Core\Breadcrumb;
use Lattice\Facades\Effects;
use Lattice\Table\Components\Table;
use Lattice\Ui\Components\Button;
use Lattice\Ui\PageSchema;

#[AsPage(route: '/admin/realms/{realm}/users', name: 'admin.realms.users', can: ManagementScope::UsersRead)]
final class RealmUsersPage extends AdminPage
{
    public function title(): string
    {
        return __('users.heading');
    }

    public function render(PageSchema $schema, Realm $realm): PageSchema
    {
        $schema->breadcrumbs($this->realmBreadcrumbs(
            $realm,
            Breadcrumb::make(__('navigation.users'), route('admin.realms.users', ['realm' => $realm->slug], false)),
        ));

        return $schema->schema([
            $this->stack(
                key: 'admin-realm-users-page',
                heading: __('users.heading'),
                description: __('users.description', ['realm' => $realm->name]),
                headerActions: ActionBar::make(primary: [
                    Button::make(__('users.create.submit'))
                        ->effects(Effects::redirect(route('admin.realms.users.create', ['realm' => $realm->slug], false))),
                ]),
                schema: [
                    Table::use(UsersTable::class),
                ],
            ),
        ]);
    }
}
