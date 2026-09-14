<?php

declare(strict_types=1);

namespace App\Auth;

use App\Admin\Enums\ManagementScope;
use App\Auth\Actions\CreateNewUser;
use App\Auth\Actions\CreateRealmUser;
use App\Auth\Actions\CreateUserFromSocialLogin;
use App\Auth\Actions\DeleteUser;
use App\Auth\Actions\ResetUserPassword;
use App\Auth\Models\User;
use App\Auth\Support\RealmUserProvider;
use App\Auth\Support\RolesTokenClaim;
use App\Auth\Support\UserClaimsResolver;
use App\Auth\Ui\Pages\DevLoginPage;
use App\Auth\Ui\Pages\RealmUsersPage;
use App\Auth\Ui\Pages\SetupTwoFactorPage;
use App\Realms\Models\Realm;
use App\Shared\Auth\Contracts\CreatesRealmUser;
use App\Shared\Auth\Contracts\DeletesUser;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Lattice\Core\Facades\Lattice;
use Lattice\Core\Support\Affix;
use Lattice\Ui\Components\MenuItem;
use Lock\Server\Authentication\Contracts\CreateUser;
use Lock\Server\Authentication\Contracts\ResetUserPassword as ResetUserPasswordContract;
use Lock\Server\Authentication\Http\Middleware\AuthenticateIdentity;
use Lock\Server\Authentication\Pipeline\LoginApi;
use Lock\Server\Authentication\Pipeline\LoginEvent;
use Lock\Server\Authentication\Pipeline\PostLoginPipeline;
use Lock\Server\Authentication\Ui\Views\FactorSetupView;
use Lock\Server\Authentication\Ui\Views\LoginView;
use Lock\Server\Realms\Http\Middleware\ResolveRealm;
use Lock\Server\Shared\Brokering\CreateUserFromSocialAccount;
use Lock\Server\Shared\Scopes\ClaimsResolver;
use Lock\Server\Tokens\Pipeline\AccessTokenPipeline;

final class AuthServiceProvider extends ServiceProvider
{
    /**
     * Package providers register first, so rebinding the server's
     * LoginView here wins.
     */
    public function register(): void
    {
        $this->app->bind(CreateUser::class, CreateNewUser::class);
        $this->app->bind(CreateUserFromSocialAccount::class, CreateUserFromSocialLogin::class);
        $this->app->bind(ResetUserPasswordContract::class, ResetUserPassword::class);
        $this->app->bind(CreatesRealmUser::class, CreateRealmUser::class);
        $this->app->bind(DeletesUser::class, DeleteUser::class);
        $this->app->bind(ClaimsResolver::class, UserClaimsResolver::class);

        $this->app->bind(FactorSetupView::class, SetupTwoFactorPage::class);

        if ($this->app->environment('local')) {
            $this->app->bind(LoginView::class, DevLoginPage::class);
        }
    }

    public function boot(): void
    {
        Relation::morphMap(['user' => User::class]);

        Lattice::extend(
            'admin.sidebar.realm',
            static fn (Realm $realm): MenuItem => MenuItem::fromPage(RealmUsersPage::class, ['realm' => $realm->slug])
                ->label(__('navigation.users'))
                ->prefix(Affix::icon('users'))
                ->can(ManagementScope::UsersRead),
            priority: 10,
        );

        Auth::provider('realm-eloquent', fn ($app, array $config): RealmUserProvider => new RealmUserProvider(
            $app['hash'],
            $config['model'],
        ));

        Lattice::context('user', User::class);
        Lattice::endpoints('account', prefix: 'account/lattice', middleware: [ResolveRealm::class, 'web', AuthenticateIdentity::class.':identity']);

        /*
         * The user provider already hides blocked users; this catches the
         * logins that resolve the user another way (a passkey, a social
         * account, a registration).
         */
        $this->app->make(PostLoginPipeline::class)->register(function (LoginEvent $event, LoginApi $api): void {
            if ($event->user instanceof User && $event->user->isBlocked()) {
                $api->deny('blocked');
            }
        });

        RolesTokenClaim::register($this->app->make(AccessTokenPipeline::class));
    }
}
