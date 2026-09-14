<?php

declare(strict_types=1);

namespace App\Shared\Ui\Pages;

use App\Realms\Models\Realm;
use Lattice\Core\Attributes\AsPage;
use Lattice\Core\Breadcrumb;
use Lattice\Http\Page;
use Lattice\Ui\Components\Component;
use Lattice\Ui\Components\Heading;
use Lattice\Ui\Components\Stack;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Contracts\SchemaEntry;
use Lattice\Ui\Enums\Align;
use Lattice\Ui\Enums\Gap;
use Lattice\Ui\Enums\Orientation;
use Lattice\Ui\Enums\Side;
use Lattice\Ui\Enums\Width;

#[AsPage(layout: 'admin', middleware: ['web', 'auth', 'verified'])]
abstract class AdminPage extends Page
{
    /**
     * @return array<int, Breadcrumb>
     */
    protected function realmBreadcrumbs(Realm $realm, Breadcrumb ...$trail): array
    {
        return [Breadcrumb::make($realm->name, route('admin.realms.users', ['realm' => $realm->slug], false)), ...array_values($trail)];
    }

    /**
     * @param  array<int, Component>  $headerActions
     * @param  array<int, SchemaEntry>  $schema
     */
    protected function stack(string $key, string $heading, ?string $description = null, array $headerActions = [], array $schema = [], Width $width = Width::ExtraLarge): Stack
    {
        $copy = [Heading::make($heading, 1)];

        if ($description !== null) {
            $copy[] = Text::make($description);
        }

        $header = [Stack::make('page-header-copy')->width(Width::Fill)->gap(Gap::Small)->schema($copy)];

        if ($headerActions !== []) {
            $header[] = Stack::make('page-header-actions')
                ->float(Side::End)
                ->width(Width::Auto)
                ->direction(Orientation::Horizontal)
                ->align(Align::Center)
                ->gap(Gap::Small)
                ->schema($headerActions);
        }

        return Stack::make($key)
            ->gap(Gap::Large)
            ->width($width)
            ->schema([
                Stack::make('page-header')
                    ->direction(Orientation::Horizontal)
                    ->align(Align::Start)
                    ->class('max-sm:flex-col max-sm:items-stretch')
                    ->schema($header),
                ...$schema,
            ]);
    }
}
