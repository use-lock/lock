<?php
declare(strict_types=1);

namespace App\Audit\Ui\Fragments;

use App\Audit\Models\AdminEvent;
use App\Audit\Ui\Concerns\RendersEventContext;
use Lattice\Fragments\FragmentDefinition;
use Lattice\Ui\Components\Stack;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Enums\Gap;
use Lattice\Ui\PageSchema;

abstract class AdminEventContextFragment extends FragmentDefinition
{
    use RendersEventContext;

    abstract protected function adminEvent(): ?AdminEvent;

    public function schema(PageSchema $schema): PageSchema
    {
        $event = $this->adminEvent();

        if (! $event instanceof AdminEvent) {
            return $schema->schema([Text::make(__('audit.events.detail.empty'))]);
        }

        $lines = $this->detailLines([
            __('audit.events.detail.session') => $event->sid,
            __('audit.events.detail.ip') => $event->ip,
            __('audit.events.detail.user-agent') => $event->user_agent,
        ], $event->context ?? []);

        if ($lines === []) {
            return $schema->schema([Text::make(__('audit.events.detail.empty'))]);
        }

        return $schema->schema([
            Stack::make('admin-event-context-'.$event->getKey())->gap(Gap::Small)->schema($lines),
        ]);
    }
}
