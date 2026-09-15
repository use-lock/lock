<?php
declare(strict_types=1);

namespace App\Realms\Ui\Actions;

use App\Admin\Enums\ManagementScope;
use App\Realms\Actions\CreateSocialProvider;
use App\Realms\Data\CreateSocialProviderData;
use App\Realms\Data\SocialProviderCredentialsData;
use App\Realms\Enums\SocialProviderDriver;
use App\Realms\Ui\Concerns\BuildsSocialProviderFields;
use App\Realms\Ui\Concerns\ResolvesRealmSocialProvider;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsAction;
use Lattice\Form\FormData;
use Lattice\Ui\Enums\Variant;

#[AsAction('admin.social-providers.create', can: ManagementScope::SocialProvidersWrite)]
final class CreateSocialProviderAction extends ActionDefinition
{
    use BuildsSocialProviderFields;
    use ResolvesRealmSocialProvider;

    public function __construct(private readonly CreateSocialProvider $createProvider) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('social-providers.create.label'))
            ->form($this->socialProviderCreationFields($this->realm()));
    }

    public function handle(FormData $data): ActionResult
    {
        $driver = SocialProviderDriver::from((string) $data->string('driver'));

        $realm = $this->realm();

        $provider = $this->createProvider->handle(
            $realm,
            new CreateSocialProviderData(
                trim((string) $data->string('key')),
                $driver,
                SocialProviderCredentialsData::from($this->submittedCredentials($driver, $data)),
                $data->boolean('enabled'),
            ),
        );

        return ActionResult::success()
            ->toast(__('social-providers.created'), Variant::Success)
            ->toRoute('admin.realms.social-providers.show', ['realm' => $realm->slug, 'socialProvider' => $provider->id]);
    }
}
