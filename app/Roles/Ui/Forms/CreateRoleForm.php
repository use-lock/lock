<?php

declare(strict_types=1);

namespace App\Roles\Ui\Forms;

use App\Admin\Enums\ManagementScope;
use App\Roles\Actions\CreateRole;
use App\Roles\Ui\Concerns\BuildsRoleFields;
use App\Roles\Ui\Concerns\ResolvesRole;
use Illuminate\Http\Request;
use Lattice\Facades\Effects;
use Lattice\Form\Attributes\AsForm;
use Lattice\Form\Components\Form;
use Lattice\Form\FormData;
use Lattice\Form\FormDefinition;
use Lattice\Http\LatticeResponse;
use Lattice\Ui\Enums\Variant;

#[AsForm('admin.realm-roles.create-form', can: ManagementScope::RolesWrite)]
final class CreateRoleForm extends FormDefinition
{
    use BuildsRoleFields;
    use ResolvesRole;

    public function __construct(private readonly CreateRole $createRole) {}

    public function definition(Form $form, Request $request): Form
    {
        return $form->schema($this->realmRoleFields($this->realm(), null))
            ->submitLabel(__('roles.create.label'));
    }

    public function handle(FormData $data): LatticeResponse
    {
        $realm = $this->realm();
        $description = trim((string) $data->string('description'));
        $role = $this->createRole->handle(
            $realm,
            (string) $data->string('name'),
            $description === '' ? null : $description,
            $this->submittedScopeIds($data->get('scope_ids', [])),
        );

        return Effects::respond()->toast(__('roles.created'), Variant::Success)
            ->toRoute('admin.realms.roles.show', ['realm' => $realm->slug, 'role' => $role->id]);
    }
}
