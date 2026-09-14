<?php
declare(strict_types=1);

namespace App\Resources\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Resources\Actions\DeleteResourceScope;
use App\Resources\Models\ResourceScope;
use App\Resources\Ui\Concerns\ResolvesRealmResource;
use App\Resources\Ui\Tables\ResourceScopesTable;
use Illuminate\Http\Request;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Ui\Enums\Emphasis;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\Variant;

#[AsAction('admin.resource-scopes.delete', can: ManagementScope::ResourcesWrite)]
final class DeleteResourceScopeAction extends ActionDefinition
{
    use ResolvesRealmResource;

    public function __construct(private readonly DeleteResourceScope $deleteScope) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('resources.scopes.delete.label'))
            ->method(HttpMethod::Delete)
            ->variant(Variant::Danger)
            ->emphasis(Emphasis::Ghost)
            ->confirm(__('resources.scopes.delete.confirm.title'), __('resources.scopes.delete.confirm.description'));
    }

    #[\Override]
    public function authorize(Request $request): bool
    {
        return $this->mutableResourceScopeOrNull() instanceof ResourceScope;
    }

    public function handle(Request $request): ActionResult
    {
        $this->deleteScope->handle($this->resourceScope());

        return ActionResult::success()
            ->toast(__('resources.scopes.deleted'), Variant::Success)
            ->reloadComponent(ResourceScopesTable::ID);
    }
}
