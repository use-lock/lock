<?php
declare(strict_types=1);

namespace App\Audit\Ui\Fragments;

use App\Admin\Enums\ManagementScope;
use App\Audit\Models\UserEvent;
use App\Audit\Ui\Concerns\RendersEventContext;
use App\Realms\Models\Realm;
use Lattice\Core\Attributes\AsFragment;
use Lattice\Fragments\FragmentDefinition;
use Lattice\Ui\Components\Stack;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Enums\Gap;
use Lattice\Ui\PageSchema;

#[AsFragment('admin.realm-user-event-context', can: ManagementScope::UserEventsRead)]
final class RealmUserEventContextFragment extends FragmentDefinition
{
    use RendersEventContext;

    public function schema(PageSchema $schema): PageSchema
    {
        $event = $this->userEvent();

        if (! $event instanceof UserEvent) {
            return $schema->schema([Text::make(__('audit.events.detail.empty'))]);
        }

        $lines = $this->detailLines([
            __('audit.events.detail.client') => $event->client_id,
            __('audit.events.detail.session') => $event->sid,
            __('audit.events.detail.ip') => $event->ip,
            __('audit.events.detail.user-agent') => $event->user_agent,
        ], $event->context ?? []);

        if ($lines === []) {
            return $schema->schema([Text::make(__('audit.events.detail.empty'))]);
        }

        return $schema->schema([
            Stack::make('user-event-context-'.$event->getKey())->gap(Gap::Small)->schema($lines),
        ]);
    }

    private function userEvent(): ?UserEvent
    {
        $realm = $this->contextModelOrNull('realm', Realm::class);

        return $realm instanceof Realm ? UserEvent::forRealm($realm)->find($this->contextString('userEvent')) : null;
    }
}
