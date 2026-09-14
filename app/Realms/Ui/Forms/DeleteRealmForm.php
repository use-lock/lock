<?php
declare(strict_types=1);

namespace App\Realms\Ui\Forms;

use App\Admin\Enums\ManagementScope;
use App\Realms\Actions\DeleteRealm;
use App\Realms\Models\Realm;
use Closure;
use Illuminate\Http\Request;
use Lattice\Facades\Effects;
use Lattice\Form\Attributes\AsForm;
use Lattice\Form\Components\Form as FormComponent;
use Lattice\Form\Components\TextInput;
use Lattice\Form\FormDefinition;
use Lattice\Http\LatticeResponse;
use Lattice\Ui\Enums\Emphasis;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\Variant;

#[AsForm('admin.realms.delete', can: ManagementScope::RealmsWrite)]
final class DeleteRealmForm extends FormDefinition
{
    public function __construct(private readonly DeleteRealm $deleteRealm) {}

    #[\Override]
    public function authorize(Request $request): bool
    {
        $realm = $this->contextModelOrNull('realm', Realm::class);

        return $realm instanceof Realm && ! $realm->isMaster();
    }

    public function definition(FormComponent $form, Request $request): FormComponent
    {
        $realm = $this->realm();

        return $form
            ->method(HttpMethod::Delete)
            ->schema([
                TextInput::make('name', __('realms.danger.confirm-name'))
                    ->placeholder($realm->name)
                    ->required()
                    ->rules(['string'])
                    ->rules(fn (): array => [function (string $attribute, mixed $value, Closure $fail) use ($realm): void {
                        if ((string) $value !== $realm->name) {
                            $fail(__('realms.danger.name-mismatch'));
                        }
                    }]),
            ])
            ->submitLabel(__('realms.danger.submit'))
            ->submitEmphasis(Emphasis::Outline);
    }

    public function handle(): LatticeResponse
    {
        $this->deleteRealm->handle($this->realm());

        return Effects::respond()->toast(__('realms.deleted'), Variant::Success)
            ->toRoute('admin.realms');
    }

    private function realm(): Realm
    {
        return $this->contextModel('realm', Realm::class);
    }
}
