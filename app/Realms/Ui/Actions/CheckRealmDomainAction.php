<?php
declare(strict_types=1);

namespace App\Realms\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Realms\Actions\CheckRealmDomain;
use App\Realms\Enums\RealmDomainStatus;
use App\Realms\Models\Realm;
use Illuminate\Http\Request;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Ui\Enums\Variant;

#[AsAction('admin.realms.domain.check', can: ManagementScope::RealmsWrite)]
final class CheckRealmDomainAction extends ActionDefinition
{
    public function __construct(private readonly CheckRealmDomain $checkDomain) {}

    public function definition(Action $action): Action
    {
        return $action->label(__('realms.domain.check'));
    }

    #[\Override]
    public function authorize(Request $request): bool
    {
        $realm = $this->contextModelOrNull('realm', Realm::class);

        return $realm instanceof Realm && ! $realm->isMaster();
    }

    public function handle(Request $request): ActionResult
    {
        $realm = $this->contextModel('realm', Realm::class);

        $status = $this->checkDomain->handle($realm);

        return ActionResult::success()
            ->toast(
                __('realms.domain.outcome.'.$status->value, ['domain' => $realm->domain]),
                $status === RealmDomainStatus::Verified ? Variant::Success : Variant::Warning,
            )
            ->reloadPage();
    }
}
