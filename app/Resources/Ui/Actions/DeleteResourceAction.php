<?php
declare(strict_types=1);

namespace App\Resources\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Resources\Actions\DeleteResource;
use App\Resources\Models\Resource;
use App\Resources\Ui\Concerns\ResolvesRealmResource;
use Illuminate\Http\Request;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Ui\Enums\Emphasis;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\Variant;

#[AsAction('admin.resources.delete', can: ManagementScope::ResourcesWrite)]
final class DeleteResourceAction extends ActionDefinition
{
    use ResolvesRealmResource;

    public function __construct(private readonly DeleteResource $deleteResource) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('resources.delete.label'))
            ->method(HttpMethod::Delete)
            ->variant(Variant::Danger)
            ->emphasis(Emphasis::Ghost)
            ->confirm(__('resources.delete.confirm.title'), __('resources.delete.confirm.description'));
    }

    #[\Override]
    public function authorize(Request $request): bool
    {
        return $this->mutableResourceOrNull() instanceof Resource;
    }

    public function handle(Request $request): ActionResult
    {
        $realm = $this->realm();

        $this->deleteResource->handle($this->resource());

        return ActionResult::success()
            ->toast(__('resources.deleted'), Variant::Success)
            ->toRoute('admin.realms.resources', ['realm' => $realm->slug]);
    }
}
