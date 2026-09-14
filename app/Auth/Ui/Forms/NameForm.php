<?php
declare(strict_types=1);

namespace App\Auth\Ui\Forms;

use Illuminate\Http\Request;
use Lattice\Form\Attributes\AsForm;
use Lattice\Form\Components\Form;
use Lattice\Form\Components\TextInput;
use Lattice\Form\FormData;
use Lattice\Http\LatticeResponse;

#[AsForm('profile.name')]
final class NameForm extends ProfileForm
{
    public function definition(Form $form, Request $request): Form
    {
        return $this->profileForm($form)
            ->schema([
                TextInput::make('name', __('common.field.name'))
                    ->value($this->currentUser()->name)
                    ->autoComplete('name')
                    ->placeholder(__('common.placeholder.full-name'))
                    ->required()
                    ->rules(['string', 'max:255']),
            ]);
    }

    public function handle(FormData $data): LatticeResponse
    {
        $this->currentUser()->update($data->all());

        return $this->saved(__('user.profile.updated'));
    }
}
