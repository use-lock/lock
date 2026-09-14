<?php

declare(strict_types=1);

namespace App\Auth\Ui\Actions;

use App\Auth\Actions\EndBrowserSession;
use App\Auth\Ui\Tables\BrowserSessionsTable;
use App\Shared\Concerns\ResolvesCurrentUser;
use Illuminate\Http\Request;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Ui\Enums\Emphasis;
use Lattice\Ui\Enums\Variant;

#[AsAction('account.sessions.sign-out')]
final class SignOutBrowserSession extends ActionDefinition
{
    use ResolvesCurrentUser;

    public function __construct(private readonly EndBrowserSession $endSession) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('user.sessions.sign-out.label'))
            ->emphasis(Emphasis::Ghost)
            ->confirm(
                title: __('user.sessions.sign-out.confirm.title'),
                description: __('user.sessions.sign-out.confirm.description'),
                confirmLabel: __('user.sessions.sign-out.label'),
            );
    }

    #[\Override]
    public function authorize(Request $request): bool
    {
        return $this->contextString('session') !== session()->getId();
    }

    public function handle(Request $request): ActionResult
    {
        $ended = $this->endSession->handle($this->currentUser(), $this->contextString('session'));

        if (! $ended) {
            return ActionResult::failure(__('user.sessions.sign-out.missing'));
        }

        return ActionResult::success()
            ->toast(__('user.sessions.sign-out.done'), Variant::Success)
            ->reloadComponent(BrowserSessionsTable::ID);
    }
}
