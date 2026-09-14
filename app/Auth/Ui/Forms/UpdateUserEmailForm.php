<?php
declare(strict_types=1);

namespace App\Auth\Ui\Forms;

use App\Admin\Enums\ManagementScope;
use App\Auth\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Lattice\Form\Attributes\AsForm;
use Lattice\Form\Components\Form as FormComponent;
use Lattice\Form\Components\TextInput;
use Lattice\Form\FormData;
use Lattice\Http\LatticeResponse;

#[AsForm('admin.users.update.email', can: ManagementScope::UsersWrite)]
final class UpdateUserEmailForm extends RealmUserForm
{
    public function definition(FormComponent $form, Request $request): FormComponent
    {
        $user = $this->user();

        return $this->userForm($form)->schema([
            TextInput::make('email', __('common.field.email-address'))
                ->email()
                ->value($user->email, editable: true)
                ->required()
                ->rules(['string', 'email', 'max:255', Rule::unique(User::class, 'email')->where('realm_id', $user->realm_id)->ignore($user->id)]),
        ]);
    }

    public function handle(FormData $data): LatticeResponse
    {
        return $this->save(email: (string) $data->string('email'));
    }
}
