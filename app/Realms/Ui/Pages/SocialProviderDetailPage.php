<?php
declare(strict_types=1);

namespace App\Realms\Ui\Pages;

use App\Admin\Enums\ManagementScope;
use App\Realms\Enums\SocialProviderDriver;
use App\Realms\Models\Realm;
use App\Realms\Models\RealmSocialProvider;
use App\Realms\Ui\Actions\DeleteSocialProviderAction;
use App\Realms\Ui\Forms\UpdateSocialProviderForm;
use App\Shared\Ui\Components\ActionBar;
use App\Shared\Ui\Pages\AdminPage;
use Illuminate\Support\Facades\Gate;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsPage;
use Lattice\Core\Breadcrumb;
use Lattice\Core\Enums\ColorName;
use Lattice\Form\Components\Form;
use Lattice\Ui\Components\Card;
use Lattice\Ui\Components\DescriptionList;
use Lattice\Ui\Components\Entries\BadgeEntry;
use Lattice\Ui\Components\Entries\ComponentEntry;
use Lattice\Ui\Components\Entries\DateEntry;
use Lattice\Ui\Components\Entries\Entry;
use Lattice\Ui\Components\Entries\TextEntry;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Enums\DateTimeStyle;
use Lattice\Ui\Enums\Width;
use Lattice\Ui\PageSchema;

#[AsPage(route: '/admin/realms/{realm}/social-providers/{socialProvider}', name: 'admin.realms.social-providers.show', can: ManagementScope::RealmsRead)]
final class SocialProviderDetailPage extends AdminPage
{
    public function render(PageSchema $schema, Realm $realm, RealmSocialProvider $socialProvider): PageSchema
    {
        abort_unless($socialProvider->belongsToRealm($realm), 404);

        $driver = __('social-providers.drivers.'.$socialProvider->driver->value);

        $schema->title($socialProvider->key)->breadcrumbs($this->realmBreadcrumbs(
            $realm,
            Breadcrumb::make(__('realms.sections.social'), route('admin.realms.settings', ['realm' => $realm->slug, 'tabs' => 'social'], false)),
            Breadcrumb::make($socialProvider->key, route('admin.realms.social-providers.show', ['realm' => $realm->slug, 'socialProvider' => $socialProvider->id], false)),
        ));

        return $schema->schema([
            $this->stack(
                key: 'admin-social-provider-detail-page',
                heading: $socialProvider->key,
                description: __('social-providers.detail.description', ['driver' => $driver, 'realm' => $realm->name]),
                headerActions: ActionBar::make(overflow: [Action::use(DeleteSocialProviderAction::class)], key: 'admin-social-provider'),
                schema: [$this->settingsCard($socialProvider, $driver)],
                width: Width::Large,
            ),
        ]);
    }

    private function settingsCard(RealmSocialProvider $provider, string $driver): Card
    {
        $enabled = BadgeEntry::make('enabled', __('social-providers.fields.enabled.label'), 'social-provider-enabled')
            ->value($provider->enabled ? __('common.value.yes') : __('common.value.no'))
            ->color($provider->enabled ? ColorName::Success : ColorName::Muted);

        if (Gate::allows(ManagementScope::RealmsWrite)) {
            $enabled->disclosure([Form::use(UpdateSocialProviderForm::class, ['field' => 'enabled'])]);
        }

        return Card::make(__('social-providers.detail.settings.heading'), __('social-providers.detail.settings.subtitle'))->schema([
            DescriptionList::make('social-provider-settings')->bleed()->schema([
                TextEntry::make('key', __('social-providers.columns.key'), 'social-provider-key')
                    ->value($provider->key)
                    ->description(__('social-providers.detail.key-fixed')),
                TextEntry::make('driver', __('social-providers.columns.driver'), 'social-provider-driver')
                    ->value($driver),
                ComponentEntry::make('callback_url', __('social-providers.fields.callback-url.label'), 'social-provider-callback-url')
                    ->value(Text::make($provider->callbackUrl())->class('break-all')->copyable())
                    ->description(__('social-providers.fields.callback-url.help-text')),
                ...array_map(
                    fn (string $name): Entry => $this->credentialEntry($provider, $name),
                    $provider->driver->fields(),
                ),
                $enabled,
                DateEntry::make('created_at', __('social-providers.detail.created-at'), 'social-provider-created-at')
                    ->value($provider->created_at?->toIso8601String())
                    ->style(DateTimeStyle::Long),
            ]),
        ]);
    }

    /**
     * A secret is write-only: the row says whether one is stored, and opening
     * it replaces rather than reveals it.
     */
    private function credentialEntry(RealmSocialProvider $provider, string $name): Entry
    {
        $key = str_replace('_', '-', $name);
        $label = __('social-providers.fields.'.$key.'.label');

        if (SocialProviderDriver::isSecret($name)) {
            $entry = BadgeEntry::make($name, $label, 'social-provider-'.$key)
                ->value(($provider->config[$name] ?? '') === '' ? __('social-providers.detail.secret.missing') : __('social-providers.detail.secret.set'))
                ->color(($provider->config[$name] ?? '') === '' ? ColorName::Warning : ColorName::Success);
        } else {
            $entry = TextEntry::make($name, $label, 'social-provider-'.$key)
                ->value($provider->config[$name] ?? null)
                ->placeholder(__('common.value.none'));
        }

        return Gate::allows(ManagementScope::RealmsWrite)
            ? $entry->disclosure([Form::use(UpdateSocialProviderForm::class, ['field' => $name])])
            : $entry;
    }
}
