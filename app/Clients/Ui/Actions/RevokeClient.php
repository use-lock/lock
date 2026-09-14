<?php
declare(strict_types=1);

namespace App\Clients\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Clients\Actions\SetClientRevocation;
use App\Clients\Ui\Concerns\ResolvesRealmClient;
use Illuminate\Http\Request;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Ui\Enums\Variant;
use Lock\Server\Clients\Models\Client;

#[AsAction('admin.clients.revoke', can: ManagementScope::ClientsWrite)]
final class RevokeClient extends ActionDefinition
{
    use ResolvesRealmClient;

    public function __construct(private readonly SetClientRevocation $setRevocation) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('clients.detail.revoke.label'))
            ->confirm(__('clients.detail.revoke.confirm.title'), __('clients.detail.revoke.confirm.description'));
    }

    #[\Override]
    public function authorize(Request $request): bool
    {
        $client = $this->realmClientOrNull();

        return $client instanceof Client && ! $client->snapshot()->revoked;
    }

    public function handle(Request $request): ActionResult
    {
        $this->setRevocation->handle($this->realmClient(), true);

        return ActionResult::success()
            ->toast(__('clients.detail.revoke.done'), Variant::Success)
            ->reloadPage();
    }
}
