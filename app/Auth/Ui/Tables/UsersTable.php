<?php

declare(strict_types=1);

namespace App\Auth\Ui\Tables;

use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementApi;
use App\Auth\Models\User;
use App\Auth\Ui\Actions\BlockUserAction;
use App\Auth\Ui\Actions\DeleteUserAction;
use App\Auth\Ui\Actions\EndAllUserSessionsAction;
use App\Auth\Ui\Actions\ResendUserVerification;
use App\Auth\Ui\Actions\ResetUserMfaAction;
use App\Auth\Ui\Actions\SendUserPasswordReset;
use App\Auth\Ui\Actions\UnblockUserAction;
use App\Realms\Models\Realm;
use App\Shared\Ui\Components\ActionBar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lattice\Actions\Components\Action;
use Lattice\Core\Enums\ColorName;
use Lattice\Table\Attributes\AsTable;
use Lattice\Table\Columns\BooleanColumn;
use Lattice\Table\Columns\StackColumn;
use Lattice\Table\Columns\TextColumn;
use Lattice\Table\Components\RowClick;
use Lattice\Table\Filters\TernaryFilter;
use Lattice\Table\Sources\Eloquent\EloquentTableDefinition;
use Lattice\Table\TableQuery;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Enums\ColumnWidth;
use Lattice\Ui\Enums\Size;

/**
 * @extends EloquentTableDefinition<User>
 */
#[AsTable('admin.users', can: ManagementScope::UsersRead)]
final class UsersTable extends EloquentTableDefinition
{
    /**
     * @return Builder<User>
     */
    public function builder(TableQuery $query): Builder
    {
        $realm = $this->contextModel('realm', Realm::class);

        $builder = $realm->users()->getQuery()
            ->select('users.*')
            ->selectRaw('users.email_verified_at is not null as email_verified')
            ->selectRaw('users.blocked_at is not null as blocked')
            ->selectRaw(
                '(exists (select 1 from oidc_totp_factors where oidc_totp_factors.user_id = users.id and oidc_totp_factors.confirmed_at is not null)'
                .' or exists (select 1 from passkeys where passkeys.user_id = users.id)) as mfa',
            )
            ->withExists(['roles as administrates' => fn (Builder $roles): Builder => $roles
                ->whereHas('scopes.resource', fn (Builder $resource): Builder => $resource->where('identifier', ManagementApi::RESOURCE))])
            ->withCasts(['email_verified' => 'boolean', 'blocked' => 'boolean', 'mfa' => 'boolean']);

        if ($query->sorts === []) {
            $builder->latest('id');
        }

        return $builder;
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->searchable()->visible(false),
            TextColumn::make('email')->searchable()->visible(false),
            StackColumn::make('member')
                ->label(__('users.columns.member'))
                ->width(ColumnWidth::Lg)
                ->schema([
                    Text::bound('name'),
                    Text::bound('email')->color(ColorName::Muted)->size(Size::Sm),
                ]),
            $this->flag('email_verified', __('users.columns.verified')),
            $this->flag('mfa', __('users.columns.mfa')),
            $this->flag('blocked', __('users.columns.blocked')),
            $this->flag('administrates', __('users.columns.admin')),
            TextColumn::make('created_at')
                ->label(__('users.columns.created-at'))
                ->date()
                ->sortable(),
        ];
    }

    private function flag(string $key, string $label): BooleanColumn
    {
        return BooleanColumn::make($key)->label($label)->width(ColumnWidth::Sm);
    }

    public function filters(): array
    {
        return [
            TernaryFilter::make('verified')
                ->label(__('users.filters.verified'))
                ->queries(
                    fn (Builder $users) => $users->whereNotNull('users.email_verified_at'),
                    fn (Builder $users) => $users->whereNull('users.email_verified_at'),
                ),
            TernaryFilter::make('blocked')
                ->label(__('users.filters.blocked'))
                ->queries(
                    fn (Builder $users) => $users->whereNotNull('users.blocked_at'),
                    fn (Builder $users) => $users->whereNull('users.blocked_at'),
                ),
            TernaryFilter::make('mfa')
                ->label(__('users.filters.mfa'))
                ->queries(
                    fn (Builder $users) => $users->where(fn (Builder $mfa) => $mfa
                        ->whereHas('totpFactors', fn (Builder $factors) => $factors->whereNotNull('confirmed_at'))
                        ->orWhereHas('passkeys')),
                    fn (Builder $users) => $users
                        ->whereDoesntHave('totpFactors', fn (Builder $factors) => $factors->whereNotNull('confirmed_at'))
                        ->whereDoesntHave('passkeys'),
                ),
        ];
    }

    #[\Override]
    public function rowClick(array $row): RowClick
    {
        return RowClick::make()->href(route('admin.realms.users.show', ['realm' => $this->contextString('realm'), 'user' => $row['id']], false));
    }

    /**
     * The flags come from {@see self::builder()}, so a row costs no extra
     * query.
     */
    public function actions(array $row): array
    {
        if (! Gate::allows(ManagementScope::UsersWrite)) {
            return [];
        }

        $context = ['user' => $row['id']];
        $blocked = (bool) ($row['blocked'] ?? false);

        return array_filter([ActionBar::menu('admin.users.row-actions', [
            ...($blocked ? [] : [Action::use(SendUserPasswordReset::class, $context)]),
            ...($blocked
                ? [Action::use(UnblockUserAction::class, $context)]
                : [Action::use(BlockUserAction::class, $context)]),
            ...($blocked || (bool) ($row['email_verified'] ?? false) ? [] : [Action::use(ResendUserVerification::class, $context)]),
            ...((bool) ($row['mfa'] ?? false) ? [Action::use(ResetUserMfaAction::class, $context)] : []),
            Action::use(EndAllUserSessionsAction::class, $context),
            Action::use(DeleteUserAction::class, $context),
        ])]);
    }
}
