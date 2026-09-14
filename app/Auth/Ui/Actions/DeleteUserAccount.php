<?php
declare(strict_types=1);

namespace App\Auth\Ui\Actions;

use App\Shared\Auth\Contracts\DeletesUser;
use App\Shared\Concerns\ResolvesCurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Form\Components\PasswordInput;
use Lattice\Ui\Enums\Emphasis;

#[AsAction('profile.delete-account')]
final class DeleteUserAccount extends ActionDefinition
{
    use ResolvesCurrentUser;

    public function __construct(private readonly DeletesUser $deleteUser) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('user.profile.delete-account.submit'))
            ->emphasis(Emphasis::Outline)
            ->form([
                PasswordInput::make('password', __('common.field.password'))
                    ->autoComplete('current-password')
                    ->placeholder(__('common.placeholder.password'))
                    ->required()
                    ->rules(['current_password']),
            ]);
    }

    public function handle(Request $request): ActionResult
    {
        $user = $this->currentUser();
        $login = route('identity.login', absolute: false);

        Auth::logout();

        $this->deleteUser->handle($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ActionResult::success()->to($login);
    }
}
