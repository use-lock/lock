<?php
declare(strict_types=1);

namespace App\Clients\Ui\Pages;

use App\Admin\Enums\ManagementScope;
use App\Clients\Ui\Forms\CreateClientForm;
use App\Realms\Models\Realm;
use App\Shared\Ui\Pages\AdminPage;
use Lattice\Core\Attributes\AsPage;
use Lattice\Core\Breadcrumb;
use Lattice\Form\Components\Form;
use Lattice\Ui\Components\Card;
use Lattice\Ui\Enums\Width;
use Lattice\Ui\PageSchema;

#[AsPage(route: '/admin/realms/{realm}/clients/create', name: 'admin.realms.clients.create', can: ManagementScope::ClientsWrite)]
final class CreateClientPage extends AdminPage
{
    public function title(): string
    {
        return __('clients.create.heading');
    }

    public function render(PageSchema $schema, Realm $realm): PageSchema
    {
        $schema->breadcrumbs($this->realmBreadcrumbs(
            $realm,
            Breadcrumb::make(__('navigation.clients'), route('admin.realms.clients', ['realm' => $realm->slug], false)),
            Breadcrumb::make(__('clients.create.heading'), route('admin.realms.clients.create', ['realm' => $realm->slug], false)),
        ));

        return $schema->schema([
            $this->stack(
                key: 'admin-create-client-page',
                heading: __('clients.create.heading'),
                description: __('clients.create.description', ['realm' => $realm->name]),
                schema: [
                    Card::make(__('clients.sections.general'))->schema([
                        Form::use(CreateClientForm::class),
                    ]),
                ],
                width: Width::Medium,
            ),
        ]);
    }
}
