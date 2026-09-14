<?php

declare(strict_types=1);

namespace App\Roles\Ui\Pages;

use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementApi;
use App\Realms\Models\Realm;
use App\Resources\Models\ResourceScope;
use App\Roles\Models\Role;
use App\Roles\Ui\Actions\DeleteRoleAction;
use App\Roles\Ui\Actions\UpdateRoleAction;
use App\Shared\Ui\Components\ActionBar;
use App\Shared\Ui\Pages\AdminPage;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsPage;
use Lattice\Core\Breadcrumb;
use Lattice\Core\Enums\ColorName;
use Lattice\Ui\Components\Badge;
use Lattice\Ui\Components\Card;
use Lattice\Ui\Components\DescriptionList;
use Lattice\Ui\Components\Entries\DateEntry;
use Lattice\Ui\Components\Entries\TextEntry;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Enums\DateTimeStyle;
use Lattice\Ui\Enums\Width;
use Lattice\Ui\PageSchema;

#[AsPage(route: '/admin/realms/{realm}/roles/{role}', name: 'admin.realms.roles.show', can: ManagementScope::RolesRead)]
final class RoleDetailPage extends AdminPage
{
    public function render(PageSchema $schema, Realm $realm, Role $role, ManagementApi $api): PageSchema
    {
        abort_unless($role->belongsToRealm($realm), 404);

        $role->setRelation('realm', $realm)->load('scopes.resource')->loadCount('users');
        $protected = $api->ownsRole($role);

        $schema->title($role->name)->breadcrumbs($this->realmBreadcrumbs(
            $realm,
            Breadcrumb::make(__('navigation.roles'), route('admin.realms.roles', ['realm' => $realm->slug], false)),
            Breadcrumb::make($role->name, route('admin.realms.roles.show', ['realm' => $realm->slug, 'role' => $role->id], false)),
        ));

        return $schema->schema([
            $this->stack(
                key: 'admin-role-detail-page',
                heading: $role->name,
                description: __('roles.detail.description', ['realm' => $realm->name]),
                headerActions: $protected
                    ? [Badge::make(__('roles.detail.protected.label'))->color(ColorName::Muted)]
                    : ActionBar::make(
                        primary: [Action::use(UpdateRoleAction::class)],
                        overflow: [Action::use(DeleteRoleAction::class)],
                        key: 'admin-role',
                    ),
                schema: [
                    Card::make(__('roles.detail.settings.heading'))->schema([
                        ...($protected ? [Text::make(__('roles.detail.protected.description'))] : []),
                        DescriptionList::make('role-settings')->bleed()->schema([
                            TextEntry::make('name', __('roles.fields.name.label'))->value($role->name),
                            TextEntry::make('description', __('roles.fields.description.label'))
                                ->value($role->description)->placeholder(__('common.value.none')),
                            TextEntry::make('users', __('roles.columns.users'))->value((string) $role->users_count),
                            DateEntry::make('created_at', __('roles.detail.created-at'))
                                ->value($role->created_at?->toIso8601String())->style(DateTimeStyle::Long),
                        ]),
                    ]),
                    $this->scopesCard($role),
                ],
                width: Width::Large,
            ),
        ]);
    }

    private function scopesCard(Role $role): Card
    {
        return Card::make(__('roles.fields.scopes.label'), __('roles.fields.scopes.help-text'))->schema([
            $role->scopes->isEmpty()
                ? Text::make(__('roles.detail.scopes.empty'))
                : DescriptionList::make('role-scopes')->bleed()->schema(
                    $role->scopes->sortBy(fn (ResourceScope $scope): string => $scope->resource->identifier.' '.$scope->value)
                        ->map(fn (ResourceScope $scope): TextEntry => TextEntry::make($scope->id, $scope->resource->identifier)
                            ->value($scope->value)->description($scope->description ?? ''))
                        ->values()->all(),
                ),
        ]);
    }
}
