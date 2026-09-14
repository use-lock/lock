<?php
declare(strict_types=1);

namespace App\Resources\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Resources\Actions\UpdateResourceScope;
use App\Resources\Data\UpdateResourceScopeData;
use App\Resources\Models\ResourceScope;
use App\Resources\Ui\Concerns\BuildsResourceScopeFields;
use App\Resources\Ui\Concerns\ResolvesRealmResource;
use App\Resources\Ui\Tables\ResourceScopesTable;
use Illuminate\Http\Request;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Form\FormData;
use Lattice\Ui\Enums\Emphasis;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\Variant;

#[AsAction('admin.resource-scopes.update', can: ManagementScope::ResourcesWrite)]
final class UpdateResourceScopeAction extends ActionDefinition
{
    use BuildsResourceScopeFields;
    use ResolvesRealmResource;

    public function __construct(private readonly UpdateResourceScope $updateScope) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('resources.scopes.edit.label'))
            ->method(HttpMethod::Patch)
            ->emphasis(Emphasis::Ghost)
            ->form($this->resourceScopeFields($this->resource(), $this->resourceScope()));
    }

    #[\Override]
    public function authorize(Request $request): bool
    {
        return $this->mutableResourceScopeOrNull() instanceof ResourceScope;
    }

    public function handle(FormData $data): ActionResult
    {
        $description = trim((string) $data->string('description'));

        $this->updateScope->handle(
            $this->resourceScope(),
            new UpdateResourceScopeData(
                (string) $data->string('value'),
                $description === '' ? null : $description,
            ),
        );

        return ActionResult::success()
            ->toast(__('resources.scopes.updated'), Variant::Success)
            ->reloadComponent(ResourceScopesTable::ID);
    }
}
