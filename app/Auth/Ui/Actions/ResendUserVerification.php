<?php

declare(strict_types=1);

namespace App\Auth\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Auth\Actions\ResendUserVerification as ResendVerification;
use App\Auth\Models\User;
use Illuminate\Http\Request;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Ui\Enums\Variant;

#[AsAction('admin.users.resend-verification', can: ManagementScope::UsersWrite)]
final class ResendUserVerification extends ActionDefinition
{
    public function __construct(private readonly ResendVerification $resendVerification) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('users.detail.resend-verification'));
    }

    public function handle(Request $request): ActionResult
    {
        $user = $this->contextModel('user', User::class);

        abort_unless($this->resendVerification->handle($user), 422, __('users.already-verified'));

        return ActionResult::success()
            ->toast(__('users.detail.verification-sent'), Variant::Success);
    }
}
