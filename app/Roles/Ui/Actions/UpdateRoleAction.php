<?php
declare(strict_types=1);

namespace App\Roles\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Roles\Actions\UpdateRole;
use App\Roles\Models\Role;
use App\Roles\Ui\Concerns\BuildsRoleFields;
use App\Roles\Ui\Concerns\ResolvesRole;
use App\Roles\Ui\Tables\RolesTable;
use Illuminate\Http\Request;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Form\FormData;
use Lattice\Ui\Enums\Emphasis;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\Variant;

#[AsAction('admin.realm-roles.update', can: ManagementScope::RolesWrite)]
final class UpdateRoleAction extends ActionDefinition
{
    use BuildsRoleFields;
    use ResolvesRole;

    public function __construct(private readonly UpdateRole $updateRole) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('roles.edit.label'))
            ->method(HttpMethod::Patch)
            ->emphasis(Emphasis::Ghost)
            ->form($this->realmRoleFields($this->realm(), $this->mutableRole()));
    }

    #[\Override]
    public function authorize(Request $request): bool
    {
        return $this->mutableRoleOrNull() instanceof Role;
    }

    public function handle(FormData $data): ActionResult
    {
        $description = trim((string) $data->string('description'));

        $this->updateRole->handle(
            $this->mutableRole(),
            (string) $data->string('name'),
            $description === '' ? null : $description,
            $this->submittedScopeIds($data->get('scope_ids', [])),
        );

        return ActionResult::success()
            ->toast(__('roles.updated'), Variant::Success)
            ->reloadComponent(RolesTable::ID)
            ->reloadPage();
    }
}
