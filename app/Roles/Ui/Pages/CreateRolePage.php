<?php

declare(strict_types=1);

namespace App\Roles\Ui\Pages;

use App\Admin\Enums\ManagementScope;
use App\Realms\Models\Realm;
use App\Roles\Ui\Forms\CreateRoleForm;
use App\Shared\Ui\Pages\AdminPage;
use Lattice\Core\Attributes\AsPage;
use Lattice\Core\Breadcrumb;
use Lattice\Form\Components\Form;
use Lattice\Ui\Components\Card;
use Lattice\Ui\Enums\Width;
use Lattice\Ui\PageSchema;

#[AsPage(route: '/admin/realms/{realm}/roles/create', name: 'admin.realms.roles.create', can: ManagementScope::RolesWrite)]
final class CreateRolePage extends AdminPage
{
    public function title(): string
    {
        return __('roles.create.label');
    }

    public function render(PageSchema $schema, Realm $realm): PageSchema
    {
        $schema->breadcrumbs($this->realmBreadcrumbs(
            $realm,
            Breadcrumb::make(__('navigation.roles'), route('admin.realms.roles', ['realm' => $realm->slug], false)),
            Breadcrumb::make($this->title(), route('admin.realms.roles.create', ['realm' => $realm->slug], false)),
        ));

        return $schema->schema([
            $this->stack(
                key: 'admin-create-role-page',
                heading: $this->title(),
                description: __('roles.create.description', ['realm' => $realm->name]),
                schema: [
                    Card::make(__('roles.detail.settings.heading'))->schema([
                        Form::use(CreateRoleForm::class),
                    ]),
                ],
                width: Width::Medium,
            ),
        ]);
    }
}
