<?php
declare(strict_types=1);

namespace App\Auth\Ui\Forms;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Lattice\Form\Attributes\AsForm;
use Lattice\Form\Components\Form;
use Lattice\Form\Components\Select;
use Lattice\Form\FormData;
use Lattice\Http\LatticeResponse;

#[AsForm('profile.preferences.language')]
final class LanguageForm extends ProfileForm
{
    public function definition(Form $form, Request $request): Form
    {
        $user = $this->currentUser();

        /** @var array<int, string> $locales */
        $locales = (array) config('lattice.i18n.locales', ['en']);

        $options = array_map(
            fn (string $locale) => Select::option(__('language.'.$locale), $locale),
            $locales,
        );

        return $this->profileForm($form)
            ->fill(['locale' => $user->locale ?? config('app.locale')])
            ->schema([
                Select::make('locale', __('user.preferences.language.label'))
                    ->options($options)
                    ->required()
                    ->rules(['required', Rule::in($locales)]),
            ]);
    }

    public function handle(FormData $data): LatticeResponse
    {
        $this->currentUser()->update(['locale' => $data['locale']]);

        return $this->saved(__('user.preferences.updated'));
    }
}
