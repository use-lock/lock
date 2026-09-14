<?php
declare(strict_types=1);

namespace App\Resources\Ui\Pages;

use App\Admin\Enums\ManagementScope;
use App\Realms\Models\Realm;
use App\Resources\Models\Resource;
use App\Resources\Ui\Actions\CreateResourceScopeAction;
use App\Resources\Ui\Actions\DeleteResourceAction;
use App\Resources\Ui\Actions\UpdateResourceAction;
use App\Resources\Ui\Tables\ResourceScopesTable;
use App\Shared\Ui\Components\ActionBar;
use App\Shared\Ui\Pages\AdminPage;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsPage;
use Lattice\Core\Breadcrumb;
use Lattice\Table\Components\Table;
use Lattice\Ui\Components\Card;
use Lattice\Ui\Components\DescriptionList;
use Lattice\Ui\Components\Entries\ComponentEntry;
use Lattice\Ui\Components\Entries\TextEntry;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Enums\Width;
use Lattice\Ui\PageSchema;
use Lock\Server\Shared\Realms\RealmAudiences;

#[AsPage(route: '/admin/realms/{realm}/resources/{resource}', name: 'admin.realms.resources.show', can: ManagementScope::ResourcesRead)]
final class ResourceDetailPage extends AdminPage
{
    public function render(PageSchema $schema, Realm $realm, Resource $resource, RealmAudiences $audiences): PageSchema
    {
        abort_unless($resource->belongsToRealm($realm), 404);

        $schema->title($resource->name)->breadcrumbs($this->realmBreadcrumbs(
            $realm,
            Breadcrumb::make(__('navigation.resources'), route('admin.realms.resources', ['realm' => $realm->slug], false)),
            Breadcrumb::make($resource->name, route('admin.realms.resources.show', ['realm' => $realm->slug, 'resource' => $resource->id], false)),
        ));

        return $schema->schema([
            $this->stack(
                key: 'admin-resource-detail-page',
                heading: $resource->name,
                description: __('resources.detail.description', ['realm' => $realm->name]),
                headerActions: ActionBar::make(
                    primary: [Action::use(CreateResourceScopeAction::class)],
                    overflow: [
                        Action::use(UpdateResourceAction::class),
                        Action::use(DeleteResourceAction::class),
                    ],
                    key: 'admin-resource',
                ),
                schema: [
                    $this->settingsCard($realm, $resource, $audiences),
                    Card::make(__('resources.detail.scopes.heading'))->schema([
                        Table::use(ResourceScopesTable::class),
                    ]),
                ],
                width: Width::Large,
            ),
        ]);
    }

    /**
     * The audience is shown resolved, since that is what a client sends as the
     * RFC 8707 `resource`.
     */
    private function settingsCard(Realm $realm, Resource $resource, RealmAudiences $audiences): Card
    {
        $audience = $realm->runAsCurrent(fn (): string => $resource->isAbsolute()
            ? $resource->identifier
            : $audiences->protectedResource($resource->identifier));

        return Card::make(__('resources.detail.settings.heading'), __('resources.detail.settings.subtitle'))->schema([
            DescriptionList::make('resource-settings')->bleed()->schema([
                TextEntry::make('name', __('resources.fields.name.label'), 'resource-name')->value($resource->name),
                TextEntry::make('identifier', __('resources.columns.identifier'), 'resource-identifier')->value($resource->identifier),
                ComponentEntry::make('audience', __('resources.detail.audience'), 'resource-audience')
                    ->value(Text::make($audience)->copyable()),
            ]),
        ]);
    }
}
