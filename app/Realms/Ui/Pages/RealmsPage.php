<?php
declare(strict_types=1);

namespace App\Realms\Ui\Pages;

use App\Admin\Enums\ManagementScope;
use App\Auth\Models\User;
use App\Realms\Ui\Tables\RealmsTable;
use App\Shared\Ui\Components\ActionBar;
use App\Shared\Ui\Pages\AdminPage;
use Illuminate\Http\Request;
use Lattice\Core\Attributes\AsPage;
use Lattice\Facades\Effects;
use Lattice\Table\Components\Table;
use Lattice\Ui\Components\Button;
use Lattice\Ui\PageSchema;

#[AsPage(route: '/admin/realms', name: 'admin.realms', can: ManagementScope::RealmsRead)]
final class RealmsPage extends AdminPage
{
    public function title(): string
    {
        return __('realms.heading');
    }

    public function render(PageSchema $schema, Request $request): PageSchema
    {
        $user = $request->user();

        return $schema->schema([
            $this->stack(
                key: 'admin-realms-page',
                heading: __('realms.heading'),
                description: __('realms.description'),
                headerActions: ActionBar::make(primary: [
                    Button::make(__('realms.create.submit'))
                        ->visible($user instanceof User && $user->can(ManagementScope::RealmsWrite))
                        ->effects(Effects::redirect(route('admin.realms.create', [], false))),
                ]),
                schema: [
                    Table::use(RealmsTable::class),
                ],
            ),
        ]);
    }
}
