<?php
declare(strict_types=1);

namespace App\Realms\Ui\Forms;

use App\Admin\Enums\ManagementScope;
use App\Realms\Actions\UpdateRealm;
use App\Realms\Data\RealmSetting;
use App\Realms\Data\UpdateRealmData;
use App\Realms\Enums\RealmSettingType;
use App\Realms\Models\Realm;
use App\Realms\Support\RealmConfiguration;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lattice\Facades\Effects;
use Lattice\Form\Attributes\AsForm;
use Lattice\Form\Components\Checkbox;
use Lattice\Form\Components\CheckboxGroup;
use Lattice\Form\Components\Field;
use Lattice\Form\Components\Form as FormComponent;
use Lattice\Form\Components\NumberInput;
use Lattice\Form\Components\Select;
use Lattice\Form\Components\Textarea;
use Lattice\Form\Components\TextInput;
use Lattice\Form\FormData;
use Lattice\Form\FormDefinition;
use Lattice\Http\LatticeResponse;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\Variant;
use Lock\Server\Shared\Realms\Settings\LoginMethod;
use Lock\Server\Shared\Realms\Settings\MfaRequirement;

/**
 * The input derives from the setting's declared type, so a new setting in
 * {@see RealmConfiguration} needs no form of its own.
 */
#[AsForm('admin.realms.setting', can: ManagementScope::RealmsWrite)]
final class RealmSettingForm extends FormDefinition
{
    public function __construct(private readonly UpdateRealm $updateRealm) {}

    public function definition(FormComponent $form, Request $request): FormComponent
    {
        $setting = $this->setting();
        $value = $this->realm()->configuration()[$setting->name];

        return $form
            ->method(HttpMethod::Patch)
            ->schema([$this->field($setting, $value)])
            ->submitLabel(__('common.action.save'));
    }

    public function handle(FormData $data): LatticeResponse
    {
        $realm = $this->realm();
        $setting = $this->setting();

        try {
            $this->updateRealm->handle($realm, UpdateRealmData::from([
                'settings' => [$setting->name => $this->submitted($setting, $data)],
            ]));
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                $setting->name => array_merge(...array_values($exception->errors())),
            ]);
        }

        return Effects::respond()->toast(__('realms.configuration-saved'), Variant::Success)
            ->toRoute('admin.realms.settings', ['realm' => $realm->slug, 'tabs' => $setting->section->value]);
    }

    private function field(RealmSetting $setting, mixed $value): Field
    {
        $label = __('realms.fields.'.$setting->translationKey().'.label');
        $helperText = __('realms.fields.'.$setting->translationKey().'.help-text');
        $rules = $setting->ownRules();

        return match ($setting->type) {
            RealmSettingType::Duration, RealmSettingType::Days, RealmSettingType::Count => NumberInput::make($setting->name, $label)
                ->value($value, editable: true)
                ->step($setting->name === 'totp_secret_length' ? 8 : 1)
                ->required()
                ->rules($rules)
                ->helperText($helperText),
            RealmSettingType::Text => TextInput::make($setting->name, $label)
                ->value($value, editable: true)
                ->rules($rules)
                ->helperText($helperText),
            RealmSettingType::Flag => Checkbox::make($setting->name, $label)
                ->value($value, editable: true)
                ->rules($rules)
                ->helperText($helperText),
            RealmSettingType::Factors => CheckboxGroup::make($setting->name, $label)
                ->value($value, editable: true)
                ->options([
                    CheckboxGroup::option(__('realms.factors.totp'), 'totp'),
                    CheckboxGroup::option(__('realms.factors.webauthn'), 'webauthn'),
                ])
                ->rules($rules)
                ->helperText($helperText),
            RealmSettingType::LoginMethods => CheckboxGroup::make($setting->name, $label)
                ->value($value, editable: true)
                ->options(array_map(
                    fn (LoginMethod $method): mixed => CheckboxGroup::option(__('realms.login-methods.'.$method->value), $method->value),
                    LoginMethod::cases(),
                ))
                ->rules($rules)
                ->helperText($helperText),
            RealmSettingType::MfaRequirement => Select::make($setting->name, $label)
                ->value($value, editable: true)
                ->options(array_map(
                    fn (MfaRequirement $requirement): mixed => Select::option(__('realms.mfa-requirements.'.str_replace('_', '-', $requirement->value)), $requirement->value),
                    MfaRequirement::cases(),
                ))
                ->required()
                ->rules($rules)
                ->helperText($helperText),
            RealmSettingType::StringList => Textarea::make($setting->name, $label)
                ->value(implode("\n", is_array($value) ? $value : []), editable: true)
                ->rows(4)
                ->rules(['nullable', 'string', 'max:20000'])
                ->helperText($helperText),
        };
    }

    /**
     * The stored configuration is typed, and the validator compares settings
     * against each other by both value and type, so a submitted field is cast
     * to the type its setting holds before it is merged in.
     */
    private function submitted(RealmSetting $setting, FormData $data): mixed
    {
        return match ($setting->type) {
            RealmSettingType::Duration, RealmSettingType::Days, RealmSettingType::Count => $data->integer($setting->name),
            RealmSettingType::Text => (string) $data->string($setting->name),
            RealmSettingType::Flag => $data->boolean($setting->name),
            RealmSettingType::Factors, RealmSettingType::LoginMethods => array_values(array_filter((array) $data->get($setting->name, []), is_string(...))),
            RealmSettingType::MfaRequirement => (string) $data->string($setting->name),
            RealmSettingType::StringList => array_values(array_filter(
                array_map(trim(...), preg_split('/\R/u', (string) $data->string($setting->name)) ?: []),
                fn (string $line): bool => $line !== '',
            )),
        };
    }

    private function setting(): RealmSetting
    {
        $setting = RealmConfiguration::setting($this->contextString('field'));

        abort_unless($setting instanceof RealmSetting, 404);

        return $setting;
    }

    private function realm(): Realm
    {
        return $this->contextModel('realm', Realm::class);
    }
}
