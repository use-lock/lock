<?php
declare(strict_types=1);

namespace App\Roles\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Auth\Models\User;
use App\Realms\Models\Realm;
use App\Roles\Actions\SyncUserRoles;
use App\Roles\Models\Role;
use App\Shared\Concerns\ResolvesCurrentUser;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Form\Components\CheckboxGroup;
use Lattice\Form\FormData;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\Variant;

#[AsAction('admin.users.realm-roles.update', can: ManagementScope::RolesWrite)]
final class UpdateUserRoles extends ActionDefinition
{
    use ResolvesCurrentUser;

    public function __construct(private readonly SyncUserRoles $syncRoles) {}

    public function definition(Action $action): Action
    {
        $target = $this->realmUser();
        $roles = $target->realm->roles()->orderBy('name')->get();

        return $action
            ->label(__('users.detail.realm-roles.update'))
            ->method(HttpMethod::Patch)
            ->form([
                CheckboxGroup::make('role_ids', __('users.detail.realm-roles.field.label'))
                    ->options($roles->map(fn (Role $role) => CheckboxGroup::option($role->name, $role->id))->all())
                    ->value($target->roles()->pluck('roles.id')->all(), editable: true)
                    ->rules(['array', Rule::in($roles->modelKeys())]),
            ]);
    }

    #[\Override]
    public function authorize(Request $request): bool
    {
        $target = $this->realmUserOrNull();

        return $target instanceof User && $target->isNot($this->currentUser());
    }

    public function handle(FormData $data): ActionResult
    {
        $this->syncRoles->handle($this->realmUser(), array_values(array_map(strval(...), (array) $data->get('role_ids', []))));

        return ActionResult::success()
            ->toast(__('users.detail.realm-roles.updated'), Variant::Success)
            ->reloadPage();
    }

    private function realmUser(): User
    {
        $user = $this->realmUserOrNull();

        abort_unless($user instanceof User, 404);

        return $user;
    }

    private function realmUserOrNull(): ?User
    {
        $realm = $this->contextModelOrNull('realm', Realm::class);
        $user = $this->contextModelOrNull('user', User::class);

        return $realm instanceof Realm && $user instanceof User && $user->belongsToRealm($realm) ? $user : null;
    }
}
