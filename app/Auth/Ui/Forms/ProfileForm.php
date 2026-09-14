<?php
declare(strict_types=1);

namespace App\Auth\Ui\Forms;

use App\Shared\Concerns\ResolvesCurrentUser;
use Lattice\Facades\Effects;
use Lattice\Form\Components\Form;
use Lattice\Form\FormDefinition;
use Lattice\Http\LatticeResponse;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\Variant;

abstract class ProfileForm extends FormDefinition
{
    use ResolvesCurrentUser;

    protected function profileForm(Form $form): Form
    {
        return $form
            ->method(HttpMethod::Patch)
            ->submitLabel(__('common.action.save'));
    }

    protected function saved(string $message): LatticeResponse
    {
        return Effects::respond()
            ->toast($message, Variant::Success)
            ->toRoute('account');
    }
}
