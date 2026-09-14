<?php
declare(strict_types=1);

namespace App\Realms\Ui\Concerns;

use App\Realms\Data\RealmSetting;
use App\Realms\Enums\RealmSettingSection;
use App\Realms\Enums\RealmSettingType;
use App\Realms\Models\Realm;
use App\Realms\Support\RealmConfiguration;
use App\Realms\Ui\Forms\RealmSettingForm;
use Carbon\CarbonInterval;
use Lattice\Core\Enums\ColorName;
use Lattice\Form\Components\Form;
use Lattice\Ui\Components\Badge;
use Lattice\Ui\Components\Component;
use Lattice\Ui\Components\DescriptionList;
use Lattice\Ui\Components\Entries\BadgeEntry;
use Lattice\Ui\Components\Entries\ComponentEntry;
use Lattice\Ui\Components\Entries\Entry;
use Lattice\Ui\Components\Entries\TextEntry;
use Lattice\Ui\Components\Stack;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Enums\Align;
use Lattice\Ui\Enums\Gap;
use Lattice\Ui\Enums\Justify;
use Lattice\Ui\Enums\Orientation;
use Lattice\Ui\Enums\Width;

trait BuildsRealmSettingRows
{
    private function settingRows(Realm $realm, RealmSettingSection $section, bool $manages): DescriptionList
    {
        $configuration = $realm->configuration();

        return DescriptionList::make($section->value.'-settings')->bleed()->schema(array_map(
            fn (RealmSetting $setting): Entry => $this->settingRow($setting, $configuration[$setting->name], $manages),
            RealmConfiguration::sections()[$section->value],
        ));
    }

    private function settingRow(RealmSetting $setting, mixed $value, bool $manages): Entry
    {
        $entry = $this->settingEntry($setting, $value);

        return $manages
            ? $entry->disclosure([Form::use(RealmSettingForm::class, ['field' => $setting->name])])
            : $entry;
    }

    private function settingEntry(RealmSetting $setting, mixed $value): Entry
    {
        $label = __('realms.fields.'.$setting->translationKey().'.label');
        $key = 'setting-'.$setting->translationKey();

        return match ($setting->type) {
            RealmSettingType::Duration => TextEntry::make($setting->name, $label, $key)
                ->value($this->duration((int) $value)),
            RealmSettingType::Days => TextEntry::make($setting->name, $label, $key)
                ->value((int) $value === 0 ? null : CarbonInterval::days((int) $value)->forHumans(['skip' => ['week']]))
                ->placeholder(__('realms.values.never')),
            RealmSettingType::Count => TextEntry::make($setting->name, $label, $key)
                ->value((string) (int) $value),
            RealmSettingType::Text => TextEntry::make($setting->name, $label, $key)
                ->value(is_string($value) ? $value : null)
                ->placeholder(__('realms.values.generated')),
            RealmSettingType::Flag => BadgeEntry::make($setting->name, $label, $key)
                ->value($value ? __('common.value.yes') : __('common.value.no'))
                ->color($value ? ColorName::Success : ColorName::Muted),
            RealmSettingType::Factors => ComponentEntry::make($setting->name, $label, $key)
                ->value($this->settingBadges(array_map(
                    fn (string $factor): string => __('realms.factors.'.$factor),
                    $this->settingList($value),
                ))),
            RealmSettingType::LoginMethods => ComponentEntry::make($setting->name, $label, $key)
                ->value($this->settingBadges(array_map(
                    fn (string $method): string => __('realms.login-methods.'.$method),
                    $this->settingList($value),
                ))),
            RealmSettingType::MfaRequirement => TextEntry::make($setting->name, $label, $key)
                ->value(__('realms.mfa-requirements.'.str_replace('_', '-', (string) $value))),
            RealmSettingType::StringList => ComponentEntry::make($setting->name, $label, $key)
                ->value($this->settingBadges($this->settingList($value))),
        };
    }

    /**
     * Carbon cascades a month of seconds into "1 month 2 days", which reads as
     * a rounding error rather than a lifetime, so the days are split out by
     * hand.
     */
    private function duration(int $seconds): string
    {
        return CarbonInterval::days(intdiv($seconds, 86400))
            ->hours(intdiv($seconds % 86400, 3600))
            ->minutes(intdiv($seconds % 3600, 60))
            ->seconds($seconds % 60)
            ->forHumans(['parts' => 2, 'skip' => ['week']]);
    }

    /**
     * @return list<string>
     */
    private function settingList(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }

    /**
     * @param  list<string>  $values
     */
    private function settingBadges(array $values): Component
    {
        if ($values === []) {
            return Text::make(__('common.value.none'))->color(ColorName::Muted);
        }

        return Stack::make()
            ->direction(Orientation::Horizontal)
            ->width(Width::Auto)
            ->align(Align::Center)
            ->justify(Justify::End)
            ->gap(Gap::ExtraSmall)
            ->schema(array_map(fn (string $value): Badge => Badge::make($value), $values));
    }
}
