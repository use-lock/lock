<?php
declare(strict_types=1);

namespace App\Clients\Ui\Forms;

use App\Admin\Enums\ManagementScope;
use App\Clients\Actions\UpdateClient;
use App\Clients\Ui\Concerns\BuildsClientFields;
use App\Clients\Ui\Concerns\ResolvesRealmClient;
use Illuminate\Http\Request;
use Lattice\Facades\Effects;
use Lattice\Form\Attributes\AsForm;
use Lattice\Form\Components\Field;
use Lattice\Form\Components\Form as FormComponent;
use Lattice\Form\FormData;
use Lattice\Form\FormDefinition;
use Lattice\Http\LatticeResponse;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\Variant;
use Lock\Server\Clients\Models\Client;

#[AsForm('admin.clients.update', can: ManagementScope::ClientsWrite)]
final class UpdateClientForm extends FormDefinition
{
    use BuildsClientFields;
    use ResolvesRealmClient;

    public function __construct(private readonly UpdateClient $updateClient) {}

    #[\Override]
    public function authorize(Request $request): bool
    {
        return $this->realmClientOrNull() instanceof Client;
    }

    public function definition(FormComponent $form, Request $request): FormComponent
    {
        return $form
            ->method(HttpMethod::Patch)
            ->schema([$this->clientField($this->realmClient())])
            ->submitLabel(__('common.action.save'));
    }

    public function handle(FormData $data): LatticeResponse
    {
        $realm = $this->realm();
        $client = $this->realmClient();

        $this->updateClient->handle($client, $this->clientAttributes($data, $client));

        return Effects::respond()->toast(__('clients.updated'), Variant::Success)
            ->toRoute('admin.realms.clients.show', ['realm' => $realm->slug, 'client' => $client->id]);
    }

    private function clientField(Client $client): Field
    {
        $field = $this->clientFields($client)[$this->contextString('field')] ?? null;

        abort_unless($field instanceof Field, 404);

        return $field;
    }
}
