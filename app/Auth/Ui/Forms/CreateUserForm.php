<?php
declare(strict_types=1);

namespace App\Auth\Ui\Forms;

use App\Admin\Enums\ManagementScope;
use App\Auth\Actions\CreateRealmUser;
use App\Auth\Models\User;
use App\Realms\Models\Realm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Lattice\Facades\Effects;
use Lattice\Form\Attributes\AsForm;
use Lattice\Form\Components\Choice;
use Lattice\Form\Components\Form as FormComponent;
use Lattice\Form\Components\PasswordInput;
use Lattice\Form\Components\TextInput;
use Lattice\Form\Components\Toggle;
use Lattice\Form\FormData;
use Lattice\Form\FormDefinition;
use Lattice\Http\LatticeResponse;
use Lattice\Ui\Enums\Variant;

#[AsForm('admin.users.create', can: ManagementScope::UsersWrite)]
final class CreateUserForm extends FormDefinition
{
    private const string INVITE = 'invite';

    private const string PASSWORD = 'password';

    public function __construct(private readonly CreateRealmUser $createUser) {}

    public function definition(FormComponent $form, Request $request): FormComponent
    {
        $realm = $this->realm();

        return $form->schema([
            TextInput::make('name', __('common.field.name'))
                ->required()
                ->rules(['string', 'max:255']),
            TextInput::make('email', __('common.field.email-address'))
                ->email()
                ->required()
                ->rules(['string', 'email', 'max:255', Rule::unique(User::class, 'email')->where('realm_id', $realm->id)]),
            Choice::make('credentials', __('users.fields.credentials.label'))
                ->options([
                    Choice::option(__('users.fields.credentials.invite'), self::INVITE),
                    Choice::option(__('users.fields.credentials.password'), self::PASSWORD),
                ])
                ->value(self::INVITE)
                ->required()
                ->rules([Rule::in([self::INVITE, self::PASSWORD])]),
            PasswordInput::make('password', __('common.field.password'))
                ->autoComplete('new-password')
                ->visibleWhen('credentials', self::PASSWORD)
                ->requiredWhen('credentials', self::PASSWORD)
                ->rules(['string', $realm->passwordPolicy()->rule()]),
            Toggle::make('email_verified', __('users.fields.email-verified.label'))
                ->helperText(__('users.fields.email-verified.help-text')),
        ])->submitLabel(__('users.create.submit'));
    }

    public function handle(FormData $data): LatticeResponse
    {
        $realm = $this->realm();
        $password = $data->get('password');

        $user = $this->createUser->handle(
            $realm,
            (string) $data->string('name'),
            (string) $data->string('email'),
            $data->string('credentials')->toString() === self::PASSWORD && is_string($password) ? $password : null,
            $data->boolean('email_verified'),
        );

        return Effects::respond()->toast(__('users.created'), Variant::Success)
            ->toRoute('admin.realms.users.show', ['realm' => $realm->slug, 'user' => $user->id]);
    }

    private function realm(): Realm
    {
        return $this->contextModel('realm', Realm::class);
    }
}
