<?php

declare(strict_types=1);

namespace App\Realms\Ui\Forms;

use App\Admin\Enums\ManagementScope;
use App\Realms\Actions\UpdateRealm;
use App\Realms\Data\RealmTheme;
use App\Realms\Data\RealmThemeFontFace;
use App\Realms\Data\RealmThemeSetting;
use App\Realms\Data\UpdateRealmData;
use App\Realms\Enums\RealmAppearance;
use App\Realms\Models\Realm;
use App\Realms\Support\RealmThemeConfiguration;
use App\Realms\Support\RealmThemeValue;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lattice\Facades\Effects;
use Lattice\Form\Attributes\AsForm;
use Lattice\Form\Components\Field;
use Lattice\Form\Components\Form as FormComponent;
use Lattice\Form\Components\NumberInput;
use Lattice\Form\Components\Repeater;
use Lattice\Form\Components\Select;
use Lattice\Form\Components\TextInput;
use Lattice\Form\FormData;
use Lattice\Form\FormDefinition;
use Lattice\Http\LatticeResponse;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\Variant;

/**
 * The input derives from the setting's declared type, so a new setting in
 * {@see RealmThemeConfiguration} needs no form of its own. What it accepts is
 * the action's call, which holds the API to the same grammar.
 */
#[AsForm('admin.realms.theme-setting', can: ManagementScope::RealmsWrite)]
final class RealmThemeSettingForm extends FormDefinition
{
    public const string APPEARANCE = 'appearance';

    public const string FONT_FACES = 'font-faces';

    public function __construct(private readonly UpdateRealm $updateRealm) {}

    public function definition(FormComponent $form, Request $request): FormComponent
    {
        return $form
            ->method(HttpMethod::Patch)
            ->schema([$this->fieldFor($this->contextString('field'), $this->realm()->theme)])
            ->submitLabel(__('common.action.save'));
    }

    public function handle(FormData $data): LatticeResponse
    {
        $realm = $this->realm();
        $field = $this->contextString('field');

        try {
            $this->updateRealm->handle($realm, UpdateRealmData::from(['theme' => $this->patch($field, $data)]));
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                $field === self::FONT_FACES ? 'font_faces' : 'value' => array_merge(...array_values($exception->errors())),
            ]);
        }

        return Effects::respond()->toast(__('realms.theme.saved'), Variant::Success)
            ->toRoute('admin.realms.settings', ['realm' => $realm->slug, 'tabs' => 'design']);
    }

    private function fieldFor(string $field, RealmTheme $theme): Field
    {
        return match ($field) {
            self::APPEARANCE => Select::make('value', __('realms.theme.appearance.label'))
                ->value($theme->appearance->value, editable: true)
                ->options(array_map(
                    fn (RealmAppearance $appearance): mixed => Select::option(__('realms.theme.appearance.options.'.$appearance->value), $appearance->value),
                    RealmAppearance::cases(),
                ))
                ->required()
                ->helperText(__('realms.theme.appearance.help-text')),
            self::FONT_FACES => Repeater::make('font_faces', __('realms.theme.font-faces.label'))
                ->value(array_map(fn (RealmThemeFontFace $face): array => $face->toArray(), $theme->fontFaces), editable: true)
                ->defaultItems(0)
                ->reorderable(false)
                ->addLabel(__('realms.theme.font-faces.add'))
                ->helperText(__('realms.theme.font-faces.help-text'))
                ->schema([
                    TextInput::make('family', __('realms.theme.font-faces.family'))->required(),
                    TextInput::make('file', __('realms.theme.font-faces.file'))->required(),
                    NumberInput::make('weight', __('realms.theme.font-faces.weight'))->value(400)->required(),
                    Select::make('style', __('realms.theme.font-faces.style'))
                        ->value('normal')
                        ->options([Select::option('normal', 'normal'), Select::option('italic', 'italic')])
                        ->required(),
                ]),
            default => $this->settingField($this->setting($field), $theme),
        };
    }

    private function settingField(RealmThemeSetting $setting, RealmTheme $theme): Field
    {
        $options = RealmThemeValue::options($setting->type);
        $helperText = __('realms.theme.types.'.$setting->type->value);

        if ($options !== []) {
            return Select::make('value', $setting->name)
                ->value($theme->value($setting->name), editable: true)
                ->options(array_map(fn (string $option): mixed => Select::option($option, $option), $options))
                ->helperText($helperText);
        }

        return TextInput::make('value', $setting->name)
            ->value($theme->value($setting->name), editable: true)
            ->helperText($helperText);
    }

    /**
     * @return array<string, mixed>
     */
    private function patch(string $field, FormData $data): array
    {
        return match ($field) {
            self::APPEARANCE => ['appearance' => (string) $data->string('value')],
            self::FONT_FACES => ['font_faces' => array_map(
                fn (array $row): array => [
                    'family' => (string) ($row['family'] ?? ''),
                    'file' => (string) ($row['file'] ?? ''),
                    'weight' => (int) ($row['weight'] ?? 400),
                    'style' => (string) ($row['style'] ?? 'normal'),
                ],
                array_values(array_filter((array) $data->get('font_faces', []), is_array(...))),
            )],
            default => ['values' => [$this->setting($field)->name => (string) $data->string('value')]],
        };
    }

    private function setting(string $name): RealmThemeSetting
    {
        $setting = RealmThemeConfiguration::setting($name);

        abort_unless($setting instanceof RealmThemeSetting, 404);

        return $setting;
    }

    private function realm(): Realm
    {
        return $this->contextModel('realm', Realm::class);
    }
}
