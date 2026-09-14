<?php
declare(strict_types=1);

namespace App\Clients\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Clients\Actions\RotateClientSecret as RotateClientSecretAction;
use App\Clients\Ui\Concerns\ResolvesRealmClient;
use Illuminate\Http\Request;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Ui\Enums\Variant;
use Lock\Server\Clients\Models\Client;

#[AsAction('admin.clients.rotate-secret', can: ManagementScope::ClientsWrite)]
final class RotateClientSecret extends ActionDefinition
{
    use ResolvesRealmClient;

    public function __construct(private readonly RotateClientSecretAction $rotateSecret) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('clients.detail.rotate-secret.label'))
            ->confirm(__('clients.detail.rotate-secret.confirm.title'), __('clients.detail.rotate-secret.confirm.description'));
    }

    #[\Override]
    public function authorize(Request $request): bool
    {
        $client = $this->realmClientOrNull();

        return $client instanceof Client && $client->snapshot()->confidential;
    }

    public function handle(Request $request): ActionResult
    {
        $client = $this->realmClient();

        $this->rotateSecret->handle($client);

        return ActionResult::success()
            ->toast(__('clients.detail.rotate-secret.done'), Variant::Success)
            ->toRoute('admin.realms.clients.show', ['realm' => $this->realm()->slug, 'client' => $client->id]);
    }
}
