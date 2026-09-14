<?php
declare(strict_types=1);

namespace App\Resources\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Resources\Actions\CreateResource;
use App\Resources\Data\CreateResourceData;
use App\Resources\Ui\Concerns\BuildsResourceFields;
use App\Resources\Ui\Concerns\ResolvesRealmResource;
use App\Resources\Ui\Tables\ResourcesTable;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Form\FormData;
use Lattice\Ui\Enums\Variant;

#[AsAction('admin.resources.create', can: ManagementScope::ResourcesWrite)]
final class CreateResourceAction extends ActionDefinition
{
    use BuildsResourceFields;
    use ResolvesRealmResource;

    public function __construct(private readonly CreateResource $createResource) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('resources.create.label'))
            ->form($this->resourceFields($this->realm(), null));
    }

    public function handle(FormData $data): ActionResult
    {
        $this->createResource->handle(
            $this->realm(),
            new CreateResourceData(
                identifier: trim((string) $data->string('identifier')),
                name: (string) $data->string('name'),
            ),
        );

        return ActionResult::success()
            ->toast(__('resources.created'), Variant::Success)
            ->reloadComponent(ResourcesTable::ID);
    }
}
