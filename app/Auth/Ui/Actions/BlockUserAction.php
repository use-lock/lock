<?php
declare(strict_types=1);

namespace App\Auth\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Auth\Actions\BlockUser;
use App\Auth\Models\User;
use App\Shared\Concerns\ResolvesCurrentUser;
use Illuminate\Http\Request;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Ui\Enums\Variant;

#[AsAction('admin.users.block', can: ManagementScope::UsersWrite)]
final class BlockUserAction extends ActionDefinition
{
    use ResolvesCurrentUser;

    public function __construct(private readonly BlockUser $blockUser) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('users.detail.block.label'))
            ->confirm(__('users.detail.block.confirm.title'), __('users.detail.block.confirm.description'));
    }

    #[\Override]
    public function authorize(Request $request): bool
    {
        $target = $this->contextStringOrNull('user');

        return $target !== null && $target !== $this->currentUser()->id;
    }

    public function handle(Request $request): ActionResult
    {
        $user = $this->contextModel('user', User::class);

        abort_if($user->isBlocked(), 422, __('users.already-blocked'));

        $this->blockUser->handle($user);

        return ActionResult::success()
            ->toast(__('users.detail.block.done'), Variant::Success)
            ->reloadPage();
    }
}
