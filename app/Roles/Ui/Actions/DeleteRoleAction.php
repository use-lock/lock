<?php
declare(strict_types=1);

namespace App\Roles\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Roles\Actions\DeleteRole;
use App\Roles\Models\Role;
use App\Roles\Ui\Concerns\ResolvesRole;
use Illuminate\Http\Request;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Ui\Enums\Emphasis;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\Variant;

#[AsAction('admin.realm-roles.delete', can: ManagementScope::RolesWrite)]
final class DeleteRoleAction extends ActionDefinition
{
    use ResolvesRole;

    public function __construct(private readonly DeleteRole $deleteRole) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('roles.delete.label'))
            ->method(HttpMethod::Delete)
            ->variant(Variant::Danger)
            ->emphasis(Emphasis::Ghost)
            ->confirm(__('roles.delete.confirm.title'), __('roles.delete.confirm.description'));
    }

    #[\Override]
    public function authorize(Request $request): bool
    {
        return $this->mutableRoleOrNull() instanceof Role;
    }

    public function handle(Request $request): ActionResult
    {
        $this->deleteRole->handle($this->mutableRole());

        return ActionResult::success()
            ->toast(__('roles.deleted'), Variant::Success)
            ->toRoute('admin.realms.roles', ['realm' => $this->realm()->slug]);
    }
}
