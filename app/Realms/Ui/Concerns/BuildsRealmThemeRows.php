<?php

declare(strict_types=1);

namespace App\Realms\Ui\Concerns;

use App\Realms\Data\RealmThemeSetting;
use App\Realms\Enums\RealmThemeSection;
use App\Realms\Models\Realm;
use App\Realms\Support\RealmThemeConfiguration;
use App\Realms\Support\RealmThemeFiles;
use App\Realms\Ui\Forms\RealmThemeSettingForm;
use App\Realms\Ui\Forms\UploadRealmThemeAssetForm;
use App\Realms\Ui\Tables\RealmThemeAssetsTable;
use Illuminate\Support\Str;
use Lattice\Core\Enums\ColorName;
use Lattice\Form\Components\Form;
use Lattice\Table\Components\Table;
use Lattice\Ui\Components\Card;
use Lattice\Ui\Components\Component;
use Lattice\Ui\Components\DescriptionList;
use Lattice\Ui\Components\Entries\Entry;
use Lattice\Ui\Components\Entries\TextEntry;
use Lattice\Ui\Components\Text;

trait BuildsRealmThemeRows
{
    /**
     * @return list<Component>
     */
    private function themeCards(Realm $realm): array
    {
        $missing = RealmThemeFiles::for($realm)->missing();

        return [
            Card::make(__('realms.theme.heading'), __('realms.theme.description'))->schema([
                ...($missing === [] ? [] : [
                    Text::make(__('realms.theme.missing-files', ['files' => implode(', ', $missing)]))->color(ColorName::Warning),
                ]),
                DescriptionList::make('theme-general')->bleed()->schema([
                    // Shown rather than linked: a Lattice link visits through Inertia, which cannot leave the console's host.
                    TextEntry::make('sign-in-url', __('realms.theme.sign-in-url'), 'theme-sign-in-url')
                        ->value($realm->origin().route('identity.login', absolute: false)),
                    $this->themeRow(
                        RealmThemeSettingForm::APPEARANCE,
                        __('realms.theme.appearance.label'),
                        __('realms.theme.appearance.options.'.$realm->theme->appearance->value),
                    ),
                    $this->themeRow(
                        RealmThemeSettingForm::FONT_FACES,
                        __('realms.theme.font-faces.label'),
                        $realm->theme->fontFaces === [] ? null : trans_choice('realms.theme.font-faces.count', count($realm->theme->fontFaces)),
                    ),
                ]),
            ]),
            Card::make(__('realms.theme.assets.heading'), __('realms.theme.assets.description'))->schema([
                Form::use(UploadRealmThemeAssetForm::class),
                Table::use(RealmThemeAssetsTable::class),
            ]),
            ...array_map(
                fn (RealmThemeSection $section): Card => Card::make(__('realms.theme.sections.'.$section->value))->schema([
                    DescriptionList::make('theme-'.$section->value)->bleed()->schema(array_map(
                        fn (RealmThemeSetting $setting): Entry => $this->themeRow($setting->name, $setting->name, $realm->theme->value($setting->name)),
                        RealmThemeConfiguration::section($section),
                    )),
                ]),
                RealmThemeSection::cases(),
            ),
        ];
    }

    private function themeRow(string $field, string $label, ?string $value): Entry
    {
        return TextEntry::make($field, $label, 'theme-'.Str::slug($field))
            ->value($value)
            ->placeholder(__('realms.theme.values.unset'))
            ->disclosure([Form::use(RealmThemeSettingForm::class, ['field' => $field])]);
    }
}
