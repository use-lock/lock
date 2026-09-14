<?php
declare(strict_types=1);

namespace App\Auth\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Auth\Actions\ResetUserMfa;
use App\Auth\Enums\UserAdminEvent;
use App\Auth\Models\User;
use App\Shared\Audit\Audit;
use Illuminate\Http\Request;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Ui\Enums\Variant;

#[AsAction('admin.users.reset-mfa', can: ManagementScope::UsersWrite)]
final class ResetUserMfaAction extends ActionDefinition
{
    public function __construct(private readonly ResetUserMfa $resetMfa) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('users.detail.reset-mfa.label'))
            ->confirm(__('users.detail.reset-mfa.confirm.title'), __('users.detail.reset-mfa.confirm.description'));
    }

    public function handle(Request $request): ActionResult
    {
        $user = $this->contextModel('user', User::class);

        $this->resetMfa->handle($user);

        Audit::record(UserAdminEvent::UserMfaReset, $user);

        return ActionResult::success()
            ->toast(__('users.detail.reset-mfa.done'), Variant::Success)
            ->reloadPage();
    }
}
