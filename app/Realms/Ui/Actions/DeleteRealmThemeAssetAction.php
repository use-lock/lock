<?php

declare(strict_types=1);

namespace App\Realms\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Realms\Actions\DeleteRealmThemeAsset;
use App\Realms\Models\RealmThemeAsset;
use App\Realms\Ui\Concerns\ResolvesRealmThemeAsset;
use Illuminate\Http\Request;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Ui\Enums\Emphasis;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\Variant;

#[AsAction('admin.realm-theme-assets.delete', can: ManagementScope::RealmsWrite)]
final class DeleteRealmThemeAssetAction extends ActionDefinition
{
    use ResolvesRealmThemeAsset;

    public function __construct(private readonly DeleteRealmThemeAsset $deleteAsset) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('realms.theme.assets.delete.label'))
            ->method(HttpMethod::Delete)
            ->variant(Variant::Danger)
            ->emphasis(Emphasis::Ghost)
            ->confirm(__('realms.theme.assets.delete.confirm.title'), __('realms.theme.assets.delete.confirm.description'));
    }

    #[\Override]
    public function authorize(Request $request): bool
    {
        return $this->themeAssetOrNull() instanceof RealmThemeAsset;
    }

    public function handle(Request $request): ActionResult
    {
        $realm = $this->realm();

        $this->deleteAsset->handle($this->themeAsset());

        return ActionResult::success()
            ->toast(__('realms.theme.assets.deleted'), Variant::Success)
            ->toRoute('admin.realms.settings', ['realm' => $realm->slug, 'tabs' => 'design']);
    }
}
