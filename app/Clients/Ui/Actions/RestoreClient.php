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

#[AsAction('admin.clients.restore', can: ManagementScope::ClientsWrite)]
final class RestoreClient extends ActionDefinition
{
    use ResolvesRealmClient;

    public function __construct(private readonly SetClientRevocation $setRevocation) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('clients.detail.restore.label'))
            ->confirm(__('clients.detail.restore.confirm.title'), __('clients.detail.restore.confirm.description'));
    }

    #[\Override]
    public function authorize(Request $request): bool
    {
        $client = $this->realmClientOrNull();

        return $client instanceof Client && $client->snapshot()->revoked;
    }

    public function handle(Request $request): ActionResult
    {
        $this->setRevocation->handle($this->realmClient(), false);

        return ActionResult::success()
            ->toast(__('clients.detail.restore.done'), Variant::Success)
            ->reloadPage();
    }
}
