<?php
declare(strict_types=1);

namespace App\Realms\Ui\Forms;

use App\Admin\Enums\ManagementScope;
use App\Realms\Actions\CreateRealm;
use App\Realms\Data\CreateRealmData;
use App\Realms\Enums\RealmDomainStatus;
use App\Realms\Enums\RealmSettingSection;
use App\Realms\Models\Realm;
use App\Realms\Support\RealmDomain;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Lattice\Facades\Effects;
use Lattice\Form\Attributes\AsForm;
use Lattice\Form\Components\Form as FormComponent;
use Lattice\Form\Components\TextInput;
use Lattice\Form\FormData;
use Lattice\Form\FormDefinition;
use Lattice\Http\LatticeResponse;
use Lattice\Ui\Enums\Variant;

#[AsForm('admin.realms.create', can: ManagementScope::RealmsWrite)]
final class CreateRealmForm extends FormDefinition
{
    public function __construct(
        private readonly CreateRealm $createRealm,
    ) {}

    public function definition(FormComponent $form, Request $request): FormComponent
    {
        return $form->schema([
            TextInput::make('name', __('realms.fields.name.label'))
                ->required()->rules(['string', 'max:'.Realm::NAME_MAX_LENGTH]),
            TextInput::make('slug', __('realms.fields.slug.label'))
                ->helperText(__('realms.fields.slug.help-text'))
                ->required()->rules(['string', 'max:63', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', Rule::unique('realms', 'slug')]),
            TextInput::make('domain', __('realms.fields.domain.label'))
                ->helperText(__('realms.fields.domain.help-text'))
                ->required()->rules(RealmDomain::rules()),
        ])->submitLabel(__('realms.create.submit'));
    }

    /**
     * A domain that does not reach this instance yet is expected, since DNS and
     * the proxy usually follow the realm, so the realm is kept and the outcome
     * reported.
     */
    public function handle(FormData $data): LatticeResponse
    {
        $realm = $this->createRealm->handle(CreateRealmData::from([
            'name' => (string) $data->string('name'),
            'slug' => (string) $data->string('slug'),
            'domain' => (string) $data->string('domain'),
        ]));
        $status = $realm->domain_status;

        if ($status === RealmDomainStatus::Verified) {
            return Effects::respond()->toast(__('realms.created'), Variant::Success)
                ->toRoute('admin.realms.settings', ['realm' => $realm->slug, 'tabs' => RealmSettingSection::Tokens->value]);
        }

        $outcome = __('realms.domain.outcome.'.$status->value, ['domain' => $realm->domain]);

        return Effects::respond()->toast(__('realms.created-unverified', ['outcome' => $outcome]), Variant::Warning)
            ->toRoute('admin.realms.settings', ['realm' => $realm->slug]);
    }
}
