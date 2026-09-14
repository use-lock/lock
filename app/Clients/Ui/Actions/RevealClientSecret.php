<?php
declare(strict_types=1);

namespace App\Clients\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Clients\Actions\RevealClientSecret as RevealSecret;
use App\Clients\Ui\Concerns\ResolvesRealmClient;
use Illuminate\Http\Request;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Ui\Components\CodeBlock;
use Lattice\Ui\Components\Modal;
use Lock\Server\Clients\Models\Client;

#[AsAction('admin.clients.reveal-secret', can: ManagementScope::ClientsWrite)]
final class RevealClientSecret extends ActionDefinition
{
    use ResolvesRealmClient;

    public function __construct(private readonly RevealSecret $revealSecret) {}

    public function definition(Action $action): Action
    {
        return $action->label(__('clients.detail.secret.reveal'));
    }

    #[\Override]
    public function authorize(Request $request): bool
    {
        $client = $this->realmClientOrNull();

        return $client instanceof Client && $client->snapshot()->confidential;
    }

    public function handle(): ActionResult
    {
        return ActionResult::success()->openModal(
            Modal::make('client-secret-disclosure')
                ->title(__('clients.detail.secret.heading'))
                ->schema([
                    CodeBlock::make($this->revealSecret->handle($this->realmClient()), 'client-secret-value')->copyable(),
                ]),
        );
    }
}
