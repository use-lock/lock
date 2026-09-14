<?php
declare(strict_types=1);

namespace App\Auth\Ui\Forms;

use App\Auth\Actions\UpdateRealmUser;
use App\Auth\Models\User;
use Lattice\Facades\Effects;
use Lattice\Form\Components\Form as FormComponent;
use Lattice\Form\FormDefinition;
use Lattice\Http\LatticeResponse;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\Variant;

/**
 * UpdateRealmUser writes name, address and verification state together, so an
 * untouched field is passed back as it stands.
 */
abstract class RealmUserForm extends FormDefinition
{
    public function __construct(private readonly UpdateRealmUser $updateUser) {}

    protected function userForm(FormComponent $form): FormComponent
    {
        return $form
            ->method(HttpMethod::Patch)
            ->submitLabel(__('common.action.save'));
    }

    protected function user(): User
    {
        return $this->contextModel('user', User::class);
    }

    protected function save(?string $name = null, ?string $email = null, ?bool $verified = null): LatticeResponse
    {
        $user = $this->user();

        $this->updateUser->handle(
            $user,
            $name ?? $user->name,
            $email ?? $user->email,
            $verified ?? $user->hasVerifiedEmail(),
        );

        return Effects::respond()->toast(__('users.updated'), Variant::Success)
            ->toRoute('admin.realms.users.show', ['realm' => $user->realm->slug, 'user' => $user->id]);
    }
}
