<?php

declare(strict_types=1);

namespace App\Auth\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Auth\Actions\SendUserPasswordReset as SendReset;
use App\Auth\Models\User;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\Request;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Ui\Enums\Variant;

#[AsAction('admin.users.send-password-reset', can: ManagementScope::UsersWrite)]
final class SendUserPasswordReset extends ActionDefinition
{
    public function __construct(private readonly SendReset $sendReset) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('users.detail.send-password-reset'));
    }

    public function handle(Request $request): ActionResult
    {
        $user = $this->contextModel('user', User::class);

        $status = $this->sendReset->handle($user);

        abort_unless($status === PasswordBroker::RESET_LINK_SENT, 422, __($status));

        return ActionResult::success()
            ->toast(__('users.detail.password-reset-sent'), Variant::Success);
    }
}
