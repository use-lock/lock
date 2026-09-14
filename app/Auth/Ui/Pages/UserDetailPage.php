<?php

declare(strict_types=1);

namespace App\Auth\Ui\Pages;

use App\Admin\Enums\ManagementScope;
use App\Auth\Models\User;
use App\Auth\Ui\Actions\BlockUserAction;
use App\Auth\Ui\Actions\DeleteUserAction;
use App\Auth\Ui\Actions\EndAllUserSessionsAction;
use App\Auth\Ui\Actions\ResendUserVerification;
use App\Auth\Ui\Actions\ResetUserMfaAction;
use App\Auth\Ui\Actions\SendUserPasswordReset;
use App\Auth\Ui\Actions\UnblockUserAction;
use App\Auth\Ui\Forms\UpdateUserEmailForm;
use App\Auth\Ui\Forms\UpdateUserNameForm;
use App\Auth\Ui\Forms\UpdateUserVerificationForm;
use App\Auth\Ui\Tables\UserSessionsTable;
use App\Realms\Models\Realm;
use App\Shared\Ui\Components\ActionBar;
use App\Shared\Ui\Pages\AdminPage;
use Lattice\Actions\Components\Action;
use Lattice\Core\Attributes\AsPage;
use Lattice\Core\Breadcrumb;
use Lattice\Core\Enums\ColorName;
use Lattice\Form\Components\Form;
use Lattice\Table\Components\Table;
use Lattice\Ui\Components\Badge;
use Lattice\Ui\Components\Card;
use Lattice\Ui\Components\Component;
use Lattice\Ui\Components\DescriptionList;
use Lattice\Ui\Components\Entries\BadgeEntry;
use Lattice\Ui\Components\Entries\DateEntry;
use Lattice\Ui\Components\Entries\TextEntry;
use Lattice\Ui\Enums\DateTimeStyle;
use Lattice\Ui\Enums\Width;
use Lattice\Ui\PageSchema;
use Lattice\Ui\Slot;

#[AsPage(route: '/admin/realms/{realm}/users/{user}', name: 'admin.realms.users.show', can: ManagementScope::UsersRead)]
final class UserDetailPage extends AdminPage
{
    public function render(PageSchema $schema, Realm $realm, User $user): PageSchema
    {
        abort_unless($user->belongsToRealm($realm), 404);

        $user->setRelation('realm', $realm);

        $schema->title($user->name)->breadcrumbs($this->realmBreadcrumbs(
            $realm,
            Breadcrumb::make(__('navigation.users'), route('admin.realms.users', ['realm' => $realm->slug], false)),
            Breadcrumb::make($user->name, route('admin.realms.users.show', ['realm' => $realm->slug, 'user' => $user->id], false)),
        ));

        return $schema->schema([
            $this->stack(
                key: 'admin-user-detail-page',
                heading: $user->name,
                description: __('users.detail.description', ['realm' => $realm->name]),
                headerActions: [
                    ...($user->isBlocked() ? [Badge::make(__('users.status.blocked'))->color(ColorName::Danger)] : []),
                    ...$this->headerActions($user),
                ],
                schema: [
                    $this->profileCard($user),
                    Slot::make('admin.users.detail.cards')->context(['target' => $user]),
                    $this->sessionsCard(),
                ],
                width: Width::Large,
            ),
        ]);
    }

    private function profileCard(User $user): Card
    {
        return Card::make(__('users.detail.profile.heading'), __('users.detail.profile.subtitle'))->schema([
            DescriptionList::make('user-profile')->bleed()->schema([
                TextEntry::make('name', __('common.field.name'), 'user-name')
                    ->value($user->name)
                    ->disclosure([Form::use(UpdateUserNameForm::class)]),
                TextEntry::make('email', __('common.field.email-address'), 'user-email')
                    ->value($user->email)
                    ->disclosure([Form::use(UpdateUserEmailForm::class)]),
                BadgeEntry::make('email_verified', __('users.fields.email-verified.label'), 'user-email-verified')
                    ->value($user->hasVerifiedEmail() ? __('common.value.yes') : __('common.value.no'))
                    ->color($user->hasVerifiedEmail() ? ColorName::Success : ColorName::Warning)
                    ->disclosure([Form::use(UpdateUserVerificationForm::class)]),
                TextEntry::make('realm', __('users.detail.realm'), 'user-realm')->value($user->realm->name),
                BadgeEntry::make('status', __('users.detail.status'), 'user-status')
                    ->value($user->isBlocked() ? __('users.status.blocked') : __('users.status.active'))
                    ->color($user->isBlocked() ? ColorName::Danger : ColorName::Success),
                BadgeEntry::make('mfa', __('users.detail.mfa'), 'user-mfa')
                    ->value($user->hasSecondFactor() ? __('users.status.mfa-enabled') : __('users.status.mfa-disabled'))
                    ->color($user->hasSecondFactor() ? ColorName::Success : ColorName::Muted),
                DateEntry::make('blocked_at', __('users.detail.blocked-at'), 'user-blocked-at')
                    ->value($user->blocked_at?->toIso8601String())
                    ->style(DateTimeStyle::Long)
                    ->visible($user->isBlocked()),
                DateEntry::make('created_at', __('users.detail.created-at'), 'user-created-at')
                    ->value($user->created_at?->toIso8601String())
                    ->style(DateTimeStyle::Long),
            ]),
        ]);
    }

    /**
     * A support call is about a password or a lockout; everything else sits
     * behind the menu.
     *
     * @return array<int, Component>
     */
    private function headerActions(User $user): array
    {
        return ActionBar::make(
            primary: [
                ...($user->isBlocked() ? [] : [Action::use(SendUserPasswordReset::class)]),
                ...($user->isBlocked()
                    ? [Action::use(UnblockUserAction::class)]
                    : [Action::use(BlockUserAction::class)]),
            ],
            overflow: [
                ...($user->hasVerifiedEmail() || $user->isBlocked() ? [] : [Action::use(ResendUserVerification::class)]),
                ...($user->hasSecondFactor() ? [Action::use(ResetUserMfaAction::class)] : []),
                Action::use(DeleteUserAction::class),
            ],
            key: 'admin-user',
        );
    }

    private function sessionsCard(): Card
    {
        return Card::make(__('users.detail.sessions.heading'), __('users.detail.sessions.subtitle'))
            ->headerActions([Action::use(EndAllUserSessionsAction::class)])
            ->schema([Table::use(UserSessionsTable::class),
            ]);
    }
}
