<?php

declare(strict_types=1);

namespace App\Shared\Ui\Components;

use Lattice\Actions\Components\Action;
use Lattice\Actions\Components\ActionGroup;
use Lattice\Ui\Components\Button;
use Lattice\Ui\Components\Component;
use Lattice\Ui\Components\Link;
use Lattice\Ui\Enums\Emphasis;

/**
 * An `Action` renders with whatever emphasis its definition set, and one that
 * sets none renders as bare text, so header buttons get theirs here. Menu rows
 * need none: the client styles an action group's items itself.
 */
final class ActionBar
{
    /**
     * @param  array<int, Action|Button>  $primary
     * @param  array<int, Action|Link>  $overflow
     * @return array<int, Component>
     */
    public static function make(array $primary = [], array $overflow = [], string $key = 'page'): array
    {
        $buttons = array_map(self::button(...), self::renderable($primary));

        $menu = self::menu($key.'-overflow-actions', $overflow);

        return $menu instanceof ActionGroup ? [...$buttons, $menu] : $buttons;
    }

    public static function button(Action|Button $action): Action|Button
    {
        return $action->emphasis(Emphasis::Outline);
    }

    /**
     * Null when nothing inside may render: an empty action group still draws
     * its trigger.
     *
     * @param  array<int, Action|Link>  $actions
     */
    public static function menu(string $id, array $actions): ?ActionGroup
    {
        $actions = self::renderable($actions);

        if ($actions === []) {
            return null;
        }

        return ActionGroup::make($id)->label(__('common.action.more'))->actions($actions);
    }

    /**
     * @template T of Component
     *
     * @param  array<int, T>  $components
     * @return array<int, T>
     */
    private static function renderable(array $components): array
    {
        return array_values(array_filter($components, static fn (Component $component): bool => $component->shouldRender()));
    }
}
