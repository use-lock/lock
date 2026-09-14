<?php
declare(strict_types=1);

namespace App\Resources\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Resources\Actions\UpdateResource;
use App\Resources\Data\UpdateResourceData;
use App\Resources\Models\Resource;
use App\Resources\Ui\Concerns\BuildsResourceFields;
use App\Resources\Ui\Concerns\ResolvesRealmResource;
use App\Resources\Ui\Tables\ResourcesTable;
use Illuminate\Http\Request;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Form\FormData;
use Lattice\Ui\Enums\Emphasis;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\Variant;

#[AsAction('admin.resources.update', can: ManagementScope::ResourcesWrite)]
final class UpdateResourceAction extends ActionDefinition
{
    use BuildsResourceFields;
    use ResolvesRealmResource;

    public function __construct(private readonly UpdateResource $updateResource) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('resources.edit.label'))
            ->method(HttpMethod::Patch)
            ->emphasis(Emphasis::Ghost)
            ->form($this->resourceFields($this->realm(), $this->resource()));
    }

    #[\Override]
    public function authorize(Request $request): bool
    {
        return $this->mutableResourceOrNull() instanceof Resource;
    }

    public function handle(FormData $data): ActionResult
    {
        $this->updateResource->handle(
            $this->resource(),
            new UpdateResourceData(
                identifier: trim((string) $data->string('identifier')),
                name: (string) $data->string('name'),
            ),
        );

        return ActionResult::success()
            ->toast(__('resources.updated'), Variant::Success)
            ->reloadComponent(ResourcesTable::ID)
            ->reloadPage();
    }
}
