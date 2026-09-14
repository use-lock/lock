<?php
declare(strict_types=1);

namespace App\Realms\Ui\Forms;

use App\Admin\Enums\ManagementScope;
use App\Realms\Actions\UpdateSocialProvider;
use App\Realms\Data\UpdateSocialProviderData;
use App\Realms\Models\RealmSocialProvider;
use App\Realms\Ui\Concerns\BuildsSocialProviderFields;
use App\Realms\Ui\Concerns\ResolvesRealmSocialProvider;
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

/**
 * One row of the detail page at a time; every credential the form does not
 * carry keeps the value the provider already holds, and a blank secret keeps
 * the stored one.
 */
#[AsForm('admin.social-providers.update', can: ManagementScope::RealmsWrite)]
final class UpdateSocialProviderForm extends FormDefinition
{
    use BuildsSocialProviderFields;
    use ResolvesRealmSocialProvider;

    public function __construct(private readonly UpdateSocialProvider $updateProvider) {}

    #[\Override]
    public function authorize(Request $request): bool
    {
        return $this->socialProviderOrNull() instanceof RealmSocialProvider;
    }

    public function definition(FormComponent $form, Request $request): FormComponent
    {
        return $form
            ->method(HttpMethod::Patch)
            ->schema([$this->field()])
            ->submitLabel(__('common.action.save'));
    }

    public function handle(FormData $data): LatticeResponse
    {
        $provider = $this->socialProvider();
        $field = $this->fieldName();
        $this->updateProvider->handle(
            $provider,
            UpdateSocialProviderData::from($field === 'enabled'
                ? ['enabled' => $data->boolean('enabled')]
                : ['config' => [$field => trim((string) $data->string($field))]]),
        );

        return Effects::respond()->toast(__('social-providers.updated'), Variant::Success)
            ->resetForm()
            ->toRoute('admin.realms.social-providers.show', ['realm' => $this->realm()->slug, 'socialProvider' => $provider->id]);
    }

    private function field(): Field
    {
        return $this->socialProviderFields($this->socialProvider())[$this->fieldName()];
    }

    private function fieldName(): string
    {
        $field = $this->contextString('field');

        abort_unless(array_key_exists($field, $this->socialProviderFields($this->socialProvider())), 404);

        return $field;
    }
}
