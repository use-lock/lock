<?php
declare(strict_types=1);

namespace App\Auth\Ui\Forms;

use Illuminate\Http\Request;
use Lattice\Facades\Effects;
use Lattice\Form\Attributes\AsForm;
use Lattice\Form\Components\Form;
use Lattice\Form\Components\PasswordInput;
use Lattice\Http\LatticeResponse;
use Lattice\Ui\Components\Grid;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\Variant;
use Lock\Server\Authentication\Actions\UpdatePassword;

#[AsForm('profile.password')]
final class PasswordForm extends ProfileForm
{
    public function __construct(private readonly UpdatePassword $updatePassword) {}

    public function definition(Form $form, Request $request): Form
    {
        $policy = $this->currentUser()->realm->passwordPolicy()->rule();

        return $this->profileForm($form)
            ->method(HttpMethod::Put)
            ->schema([
                Grid::make('password-fields')
                    ->columns(1)
                    ->schema([
                        PasswordInput::make('current_password', __('user.security.password.current'))
                            ->autoComplete('current-password')
                            ->placeholder(__('user.security.password.current'))
                            ->required()
                            ->rules(['current_password']),
                        PasswordInput::make('password', __('user.security.password.new'))
                            ->autoComplete('new-password')
                            ->placeholder(__('user.security.password.new'))
                            ->passwordRules($policy->toPasswordRulesString())
                            ->needsConfirmation()
                            ->required()
                            ->rules(['string', 'confirmed', $policy]),
                    ]),
            ])
            ->resetOnError(['password', 'password_confirmation', 'current_password'])
            ->resetOnSuccess();
    }

    public function handle(Request $request): LatticeResponse
    {
        ($this->updatePassword)($this->currentUser(), $request->only('password', 'password_confirmation'));

        return Effects::respond()->toast(__('user.security.password.updated'), Variant::Success)->back();
    }
}
