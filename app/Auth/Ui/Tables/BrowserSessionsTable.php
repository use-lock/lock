<?php

declare(strict_types=1);

namespace App\Auth\Ui\Tables;

use App\Auth\Support\BrowserSession;
use App\Auth\Support\BrowserSessions;
use App\Auth\Ui\Actions\SignOutBrowserSession;
use App\Shared\Concerns\ResolvesCurrentUser;
use App\Shared\Ui\Components\ActionBar;
use Lattice\Actions\Components\Action;
use Lattice\Core\Enums\ColorName;
use Lattice\Table\Attributes\AsTable;
use Lattice\Table\CallbackTableSource;
use Lattice\Table\Columns\BadgeColumn;
use Lattice\Table\Columns\StackColumn;
use Lattice\Table\Columns\TextColumn;
use Lattice\Table\Contracts\TableSource;
use Lattice\Table\Enums\PaginationType;
use Lattice\Table\TableDefinition;
use Lattice\Table\TableQuery;
use Lattice\Table\TableResult;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Enums\Size;

#[AsTable(BrowserSessionsTable::ID)]
final class BrowserSessionsTable extends TableDefinition
{
    use ResolvesCurrentUser;

    public const string ID = 'account.sessions';

    public function __construct(private readonly BrowserSessions $sessions) {}

    public function pagination(): PaginationType
    {
        return PaginationType::None;
    }

    public function emptyLabel(): string
    {
        return __('user.sessions.empty');
    }

    public function columns(): array
    {
        return [
            StackColumn::make('device')
                ->label(__('user.sessions.columns.device'))
                ->schema([
                    Text::bound('device'),
                    Text::bound('ip_address')->color(ColorName::Muted)->size(Size::Sm),
                ]),
            TextColumn::make('last_active')->label(__('user.sessions.columns.last-active')),
            BadgeColumn::make('status')
                ->label(__('user.sessions.columns.status'))
                ->options([
                    'current' => __('user.sessions.status.current'),
                    'active' => __('user.sessions.status.active'),
                ])
                ->colors(['current' => 'green', 'active' => 'gray']),
        ];
    }

    public function actions(array $row): array
    {
        if ($row['status'] === 'current') {
            return [];
        }

        return array_filter([ActionBar::menu('account.sessions.row-actions', [
            Action::use(SignOutBrowserSession::class, ['session' => $row['id']]),
        ])]);
    }

    public function source(): TableSource
    {
        return new CallbackTableSource(fn (TableQuery $query): TableResult => TableResult::fromItems(
            $this->sessions->forUser($this->currentUser())
                ->map(fn (BrowserSession $session): array => $this->row($session))
                ->all(),
        ));
    }

    /**
     * @return array{id: string, device: string, ip_address: string, last_active: string, status: string}
     */
    private function row(BrowserSession $session): array
    {
        return [
            'id' => $session->id,
            'device' => $this->device($session),
            'ip_address' => (string) $session->ipAddress,
            'last_active' => $session->lastActivity->diffForHumans(),
            'status' => $session->id === session()->getId() ? 'current' : 'active',
        ];
    }

    private function device(BrowserSession $session): string
    {
        $parts = array_filter([$session->browser(), $session->platform()]);

        return $parts === [] ? __('user.sessions.unknown-device') : implode(' · ', $parts);
    }
}
