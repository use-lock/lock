<?php
declare(strict_types=1);

namespace App\Auth\Ui\Tables;

use App\Admin\Enums\ManagementScope;
use App\Auth\Models\User;
use App\Auth\Ui\Actions\EndUserSessionAction;
use App\Realms\Models\Realm;
use App\Shared\Ui\Components\ActionBar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lattice\Actions\Components\Action;
use Lattice\Table\Attributes\AsTable;
use Lattice\Table\Columns\BadgeColumn;
use Lattice\Table\Columns\NumberColumn;
use Lattice\Table\Columns\TextColumn;
use Lattice\Table\Sources\Eloquent\EloquentTableDefinition;
use Lattice\Table\TableQuery;
use Lattice\Ui\Enums\DateTimeStyle;
use Lock\Server\Sessions\Models\OidcSession;
use Lock\Server\Sessions\Models\SessionParticipant;

/**
 * @extends EloquentTableDefinition<OidcSession>
 */
#[AsTable(UserSessionsTable::ID, can: ManagementScope::UsersRead)]
final class UserSessionsTable extends EloquentTableDefinition
{
    public const string ID = 'admin.users.sessions';

    private const string ACTIVE = 'active';

    private const string EXPIRED = 'expired';

    private const string REVOKED = 'revoked';

    #[\Override]
    public function authorize(Request $request): bool
    {
        $realm = $this->contextModelOrNull('realm', Realm::class);
        $user = $this->contextModelOrNull('user', User::class);

        return $realm instanceof Realm && $user instanceof User && $user->belongsToRealm($realm);
    }

    /**
     * @return Builder<OidcSession>
     */
    public function builder(TableQuery $query): Builder
    {
        $user = $this->user();

        $builder = OidcSession::query()
            ->where('oidc_sessions.realm', $user->realm->slug)
            ->where('oidc_sessions.user_id', $user->id)
            ->select('oidc_sessions.*')
            ->selectRaw(
                'case when oidc_sessions.revoked_at is not null then ? when oidc_sessions.expires_at <= ? then ? else ? end as status',
                [self::REVOKED, now(), self::EXPIRED, self::ACTIVE],
            )
            ->selectSub(
                SessionParticipant::query()->selectRaw('count(*)')->whereColumn('oidc_session_participants.session_id', 'oidc_sessions.id'),
                'participants_count',
            );

        if ($query->sorts === []) {
            $builder->latest('oidc_sessions.created_at');
        }

        return $builder;
    }

    public function columns(): array
    {
        return [
            TextColumn::make('id')->label(__('users.detail.sessions.columns.sid'))->copyable(),
            BadgeColumn::make('status')
                ->label(__('users.detail.sessions.columns.status'))
                ->options([
                    BadgeColumn::option(__('users.detail.sessions.status.active'), self::ACTIVE),
                    BadgeColumn::option(__('users.detail.sessions.status.expired'), self::EXPIRED),
                    BadgeColumn::option(__('users.detail.sessions.status.revoked'), self::REVOKED),
                ])
                ->colors([self::ACTIVE => 'green', self::EXPIRED => 'gray', self::REVOKED => 'red']),
            NumberColumn::make('participants_count')->label(__('users.detail.sessions.columns.clients')),
            TextColumn::make('created_at')->label(__('users.detail.sessions.columns.started-at'))->dateTime(DateTimeStyle::Short)->sortable(),
            TextColumn::make('expires_at')->label(__('users.detail.sessions.columns.expires-at'))->dateTime(DateTimeStyle::Short)->sortable(),
        ];
    }

    #[\Override]
    public function emptyLabel(): string
    {
        return __('users.detail.sessions.empty');
    }

    public function actions(array $row): array
    {
        if (! Gate::allows(ManagementScope::UsersWrite)) {
            return [];
        }

        if (($row['status'] ?? null) !== self::ACTIVE) {
            return [];
        }

        return array_filter([ActionBar::menu('admin.users.sessions.row-actions', [
            Action::use(EndUserSessionAction::class, ['sid' => $row['id']]),
        ])]);
    }

    private function user(): User
    {
        $realm = $this->contextModel('realm', Realm::class);
        $user = $this->contextModel('user', User::class);

        abort_unless($user->belongsToRealm($realm), 404);

        return $user;
    }
}
