<?php
declare(strict_types=1);

namespace App\Clients\Ui\Pages;

use App\Admin\Enums\ManagementScope;
use App\Clients\Ui\Actions\DeleteClient;
use App\Clients\Ui\Actions\RestoreClient;
use App\Clients\Ui\Actions\RevealClientSecret;
use App\Clients\Ui\Actions\RevokeClient;
use App\Clients\Ui\Actions\RotateClientSecret;
use App\Clients\Ui\Forms\UpdateClientForm;
use App\Realms\Models\Realm;
use App\Shared\Ui\Components\ActionBar;
use App\Shared\Ui\Pages\AdminPage;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsPage;
use Lattice\Core\Breadcrumb;
use Lattice\Core\Enums\ColorName;
use Lattice\Form\Components\Form;
use Lattice\Ui\Components\Badge;
use Lattice\Ui\Components\Card;
use Lattice\Ui\Components\CodeBlock;
use Lattice\Ui\Components\Component;
use Lattice\Ui\Components\DescriptionList;
use Lattice\Ui\Components\Entries\BadgeEntry;
use Lattice\Ui\Components\Entries\ComponentEntry;
use Lattice\Ui\Components\Entries\DateEntry;
use Lattice\Ui\Components\Entries\TextEntry;
use Lattice\Ui\Components\Stack;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Enums\Align;
use Lattice\Ui\Enums\DateTimeStyle;
use Lattice\Ui\Enums\Gap;
use Lattice\Ui\Enums\Justify;
use Lattice\Ui\Enums\Orientation;
use Lattice\Ui\Enums\Size;
use Lattice\Ui\Enums\Width;
use Lattice\Ui\PageSchema;
use Lock\Server\Clients\Models\Client;

#[AsPage(route: '/admin/realms/{realm}/clients/{client}', name: 'admin.realms.clients.show', can: ManagementScope::ClientsRead)]
final class ClientDetailPage extends AdminPage
{
    public function render(PageSchema $schema, Realm $realm, Client $client): PageSchema
    {
        abort_unless($client->realm === $realm->slug, 404);

        $schema->title($client->name)->breadcrumbs($this->realmBreadcrumbs(
            $realm,
            Breadcrumb::make(__('navigation.clients'), route('admin.realms.clients', ['realm' => $realm->slug], false)),
            Breadcrumb::make($client->name, route('admin.realms.clients.show', ['realm' => $realm->slug, 'client' => $client->id], false)),
        ));

        return $schema->schema([
            $this->stack(
                key: 'admin-client-detail-page',
                heading: $client->name,
                description: __('clients.detail.description', ['realm' => $realm->name]),
                headerActions: [
                    ...($client->snapshot()->revoked ? [Badge::make(__('clients.status.blocked'))->color(ColorName::Danger)] : []),
                    ...$this->headerActions($client),
                ],
                schema: [
                    ...($client->snapshot()->confidential ? [$this->secretCard($client)] : []),
                    $this->settingsCard($client),
                ],
                width: Width::Large,
            ),
        ]);
    }

    /**
     * Rotating the secret is what an operator comes here for; the rest sits
     * behind the menu.
     *
     * @return array<int, Component>
     */
    private function headerActions(Client $client): array
    {
        return ActionBar::make(
            primary: $client->snapshot()->confidential ? [Action::use(RotateClientSecret::class)] : [],
            overflow: [
                ...($client->snapshot()->revoked
                    ? [Action::use(RestoreClient::class)]
                    : [Action::use(RevokeClient::class)]),
                Action::use(DeleteClient::class),
            ],
            key: 'admin-client',
        );
    }

    private function secretCard(Client $client): Card
    {
        return Card::make(__('clients.detail.secret.heading'), __('clients.detail.secret.subtitle'))->schema([
            Stack::make('client-secret')->gap(Gap::Small)->schema([
                Text::make(__('clients.columns.client-id'))->size(Size::Sm)->color(ColorName::Muted),
                CodeBlock::make($client->client_id, 'client-secret-client-id')->copyable(),
                Action::use(RevealClientSecret::class),
            ]),
        ]);
    }

    private function settingsCard(Client $client): Card
    {
        return Card::make(__('clients.detail.settings.heading'), __('clients.detail.settings.subtitle'))->schema([
            DescriptionList::make('client-settings')->bleed()->schema([
                TextEntry::make('name', __('clients.fields.name.label'), 'client-name')
                    ->value($client->name)
                    ->disclosure([Form::use(UpdateClientForm::class, ['field' => 'name'])]),
                ComponentEntry::make('client_id', __('clients.columns.client-id'), 'client-client-id')
                    ->value(Text::make($client->client_id)->copyable()),
                TextEntry::make('token_endpoint_auth_method', __('clients.columns.type'), 'client-type')
                    ->value(__('clients.types.'.str_replace('_', '-', $client->token_endpoint_auth_method->value)))
                    ->disclosure([Form::use(UpdateClientForm::class, ['field' => 'token_endpoint_auth_method'])]),
                ComponentEntry::make('grant_types', __('clients.columns.grant-types'), 'client-grant-types')
                    ->value($this->badges(array_map(
                        fn (string $grantType): string => __('clients.grant-types.'.str_replace('_', '-', $grantType)),
                        $client->grant_types,
                    )))
                    ->disclosure([Form::use(UpdateClientForm::class, ['field' => 'grant_types'])]),
                ComponentEntry::make('redirect_uris', __('clients.fields.redirect-uris.label'), 'client-redirect-uris')
                    ->value($this->uriLines($client->redirect_uris))
                    ->disclosure([Form::use(UpdateClientForm::class, ['field' => 'redirect_uris'])]),
                ComponentEntry::make('post_logout_redirect_uris', __('clients.fields.post-logout-redirect-uris.label'), 'client-post-logout-redirect-uris')
                    ->value($this->uriLines($client->post_logout_redirect_uris))
                    ->disclosure([Form::use(UpdateClientForm::class, ['field' => 'post_logout_redirect_uris'])]),
                BadgeEntry::make('consent_required', __('clients.fields.consent-required.label'), 'client-consent-required')
                    ->value($client->consent_required ? __('common.value.yes') : __('common.value.no'))
                    ->color($client->consent_required ? ColorName::Success : ColorName::Muted)
                    ->disclosure([Form::use(UpdateClientForm::class, ['field' => 'consent_required'])]),
                TextEntry::make('backchannel_logout_uri', __('clients.fields.backchannel-logout-uri.label'), 'client-backchannel-logout-uri')
                    ->value($client->backchannel_logout_uri)
                    ->placeholder(__('common.value.none'))
                    ->disclosure([Form::use(UpdateClientForm::class, ['field' => 'backchannel_logout_uri'])]),
                ComponentEntry::make('scopes', __('clients.detail.scopes'), 'client-scopes')
                    ->value($this->badges($client->snapshot()->assignedScopes())),
                BadgeEntry::make('status', __('clients.columns.status'), 'client-status')
                    ->value($client->snapshot()->revoked ? __('clients.status.blocked') : __('clients.status.active'))
                    ->color($client->snapshot()->revoked ? ColorName::Danger : ColorName::Success),
                DateEntry::make('created_at', __('clients.detail.created-at'), 'client-created-at')
                    ->value($client->created_at?->toIso8601String())
                    ->style(DateTimeStyle::Long),
            ]),
        ]);
    }

    /**
     * @param  array<int, string>  $values
     */
    private function badges(array $values): Component
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
            ->schema(array_map(fn (string $value): Badge => Badge::make($value), array_values($values)));
    }

    /**
     * @param  array<int, string>|null  $uris
     */
    private function uriLines(?array $uris): Component
    {
        if ($uris === null || $uris === []) {
            return Text::make(__('common.value.none'))->color(ColorName::Muted);
        }

        return Stack::make()
            ->width(Width::Auto)
            ->align(Align::End)
            ->gap(Gap::ExtraSmall)
            ->schema(array_map(
                fn (string $uri): Text => Text::make($uri)->size(Size::Sm),
                array_values($uris),
            ));
    }
}
