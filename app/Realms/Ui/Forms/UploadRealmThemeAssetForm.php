<?php

declare(strict_types=1);

namespace App\Realms\Ui\Forms;

use App\Admin\Enums\ManagementScope;
use App\Realms\Actions\StoreRealmThemeAsset;
use App\Realms\Models\Realm;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Lattice\Facades\Effects;
use Lattice\Form\Attributes\AsForm;
use Lattice\Form\Components\FileUpload;
use Lattice\Form\Components\Form as FormComponent;
use Lattice\Form\Components\TextInput;
use Lattice\Form\FormData;
use Lattice\Form\FormDefinition;
use Lattice\Http\LatticeResponse;
use Lattice\Ui\Enums\Variant;

#[AsForm('admin.realms.theme-asset', can: ManagementScope::RealmsWrite)]
final class UploadRealmThemeAssetForm extends FormDefinition
{
    public function __construct(private readonly StoreRealmThemeAsset $storeAsset) {}

    public function definition(FormComponent $form, Request $request): FormComponent
    {
        return $form
            ->schema([
                FileUpload::make('file', __('realms.theme.assets.file.label'))
                    ->maxSize(5120)
                    ->required()
                    ->helperText(__('realms.theme.assets.file.help-text')),
                TextInput::make('name', __('realms.theme.assets.name.label'))
                    ->rules(['nullable', 'string', 'max:128'])
                    ->helperText(__('realms.theme.assets.name.help-text')),
            ])
            ->submitLabel(__('realms.theme.assets.submit'));
    }

    public function handle(FormData $data): LatticeResponse
    {
        $realm = $this->contextModel('realm', Realm::class);
        $file = $data->get('file');

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => [__('realms.theme.assets.errors.content')]]);
        }

        $name = (string) $data->string('name') ?: strtolower($file->getClientOriginalName());

        $this->storeAsset->handle($realm, $name, $file->getContent());

        return Effects::respond()->toast(__('realms.theme.assets.uploaded'), Variant::Success)
            ->toRoute('admin.realms.settings', ['realm' => $realm->slug, 'tabs' => 'design']);
    }
}
