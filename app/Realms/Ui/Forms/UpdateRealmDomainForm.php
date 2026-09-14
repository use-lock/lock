<?php
declare(strict_types=1);

namespace App\Realms\Ui\Forms;

use App\Admin\Enums\ManagementScope;
use App\Realms\Actions\UpdateRealm;
use App\Realms\Data\UpdateRealmData;
use App\Realms\Enums\RealmDomainStatus;
use App\Realms\Models\Realm;
use App\Realms\Support\RealmDomain;
use Illuminate\Http\Request;
use Lattice\Facades\Effects;
use Lattice\Form\Attributes\AsForm;
use Lattice\Form\Components\Form as FormComponent;
use Lattice\Form\Components\TextInput;
use Lattice\Form\FormData;
use Lattice\Form\FormDefinition;
use Lattice\Http\LatticeResponse;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\Variant;

#[AsForm('admin.realms.domain.update', can: ManagementScope::RealmsWrite)]
final class UpdateRealmDomainForm extends FormDefinition
{
    public function __construct(
        private readonly UpdateRealm $updateRealm,
    ) {}

    #[\Override]
    public function authorize(Request $request): bool
    {
        $realm = $this->contextModelOrNull('realm', Realm::class);

        return $realm instanceof Realm && ! $realm->isMaster();
    }

    public function definition(FormComponent $form, Request $request): FormComponent
    {
        $realm = $this->realm();

        return $form
            ->method(HttpMethod::Patch)
            ->schema([
                TextInput::make('domain', __('realms.fields.domain.label'))
                    ->value($realm->domain, editable: true)
                    ->helperText(__('realms.domain.change-warning'))
                    ->required()
                    ->rules(RealmDomain::rules($realm)),
            ])
            ->submitLabel(__('realms.domain.submit'));
    }

    public function handle(FormData $data): LatticeResponse
    {
        $realm = $this->realm();
        $before = $realm->domain;

        $realm = $this->updateRealm->handle($realm, UpdateRealmData::from(['domain' => (string) $data->string('domain')]));

        if ($realm->domain === $before) {
            return Effects::respond()->toRoute('admin.realms.settings', ['realm' => $realm->slug]);
        }

        $status = $realm->domain_status;
        $outcome = __('realms.domain.outcome.'.$status->value, ['domain' => $realm->domain]);

        return Effects::respond()
            ->toast(__('realms.domain.updated').' '.$outcome, $status === RealmDomainStatus::Verified ? Variant::Success : Variant::Warning)
            ->toRoute('admin.realms.settings', ['realm' => $realm->slug]);
    }

    private function realm(): Realm
    {
        return $this->contextModel('realm', Realm::class);
    }
}
