<?php
declare(strict_types=1);

namespace App\Auth\Ui\Forms;

use App\Auth\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Lattice\Actions\Components\Action;
use Lattice\Form\Attributes\AsForm;
use Lattice\Form\Components\Form;
use Lattice\Form\Components\TextInput;
use Lattice\Form\FormData;
use Lattice\Http\LatticeResponse;
use Lattice\Ui\Components\Component;
use Lattice\Ui\Components\Stack;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Enums\Gap;
use Lattice\Ui\Enums\Orientation;
use Lock\Server\Authentication\Ui\Actions\SendVerificationEmailAction;

#[AsForm('profile.email')]
final class EmailForm extends ProfileForm
{
    public function definition(Form $form, Request $request): Form
    {
        $user = $this->currentUser();

        return $this->profileForm($form)
            ->schema([
                TextInput::make('email', __('common.field.email-address'))
                    ->email()
                    ->value($user->email)
                    ->autoComplete('username')
                    ->placeholder(__('common.field.email-address'))
                    ->required()
                    ->rules(['string', 'max:255', Rule::unique(User::class)->where('realm_id', $user->realm_id)->ignore($user->id)]),
                ...$this->verificationNotice($user, $request),
            ]);
    }

    public function handle(FormData $data): LatticeResponse
    {
        $user = $this->currentUser();

        $user->fill($data->all());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $this->saved(__('user.profile.updated'));
    }

    /**
     * @return array<int, Component>
     */
    private function verificationNotice(User $user, Request $request): array
    {
        if ($user->hasVerifiedEmail()) {
            return [];
        }

        $components = [
            Stack::make('profile-verification-notice')
                ->direction(Orientation::Horizontal)
                ->gap(Gap::ExtraSmall)
                ->schema([
                    Text::make(__('user.profile.unverified')),
                    Action::use(SendVerificationEmailAction::class),
                ]),
        ];

        if ($request->session()->get('status') === 'verification-link-sent') {
            $components[] = Text::make(__('user.profile.verification-sent'));
        }

        return $components;
    }
}
