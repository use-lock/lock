<?php
declare(strict_types=1);

namespace App\Auth\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Auth\Actions\EndUserSessions;
use App\Auth\Enums\UserAdminEvent;
use App\Auth\Models\User;
use App\Auth\Ui\Tables\UserSessionsTable;
use App\Shared\Audit\Audit;
use Illuminate\Http\Request;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Ui\Enums\Variant;

#[AsAction('admin.users.sessions.end-all', can: ManagementScope::UsersWrite)]
final class EndAllUserSessionsAction extends ActionDefinition
{
    public function __construct(private readonly EndUserSessions $endSessions) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('users.detail.sessions.end-all.label'))
            ->confirm(__('users.detail.sessions.end-all.confirm.title'), __('users.detail.sessions.end-all.confirm.description'));
    }

    public function handle(Request $request): ActionResult
    {
        $user = $this->contextModel('user', User::class);

        $ended = $this->endSessions->handle($user);

        Audit::record(UserAdminEvent::UserSessionsEnded, $user, context: ['count' => $ended]);

        return ActionResult::success()
            ->toast(__('users.detail.sessions.end-all.done', ['count' => $ended]), Variant::Success)
            ->reloadComponent(UserSessionsTable::ID);
    }
}
