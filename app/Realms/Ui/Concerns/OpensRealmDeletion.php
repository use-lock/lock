<?php

declare(strict_types=1);

namespace App\Realms\Ui\Concerns;

use App\Admin\Enums\ManagementScope;
use App\Realms\Ui\Forms\DeleteRealmForm;
use Lattice\Form\Components\Form;
use Lattice\Ui\Components\Link;
use Lattice\Ui\Components\Modal;

/**
 * Deleting a realm is a form, because the name has to be typed back; the
 * trigger opens it in a modal so it can sit in an action menu.
 */
trait OpensRealmDeletion
{
    private function deleteRealmTrigger(string $slug): Link
    {
        return Link::make(__('realms.danger.submit'), 'delete-realm-'.$slug)
            ->unstyled()
            ->can(ManagementScope::RealmsWrite)
            ->visible($slug !== config('lock.master_realm'))
            ->modal(
                Modal::make('admin.realms.delete.'.$slug)
                    ->title(__('realms.danger.heading'))
                    ->description(__('realms.danger.subtitle'))
                    ->schema([Form::use(DeleteRealmForm::class, ['realm' => $slug])]),
            );
    }
}
