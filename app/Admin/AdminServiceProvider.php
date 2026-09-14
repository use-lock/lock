<?php

declare(strict_types=1);

namespace App\Admin;

use App\Admin\Enums\ManagementScope;
use App\Admin\Ui\Pages\ApiReferencePage;
use App\Auth\Models\User;
use App\Shared\Auth\Support\RequestUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Lattice\Core\Facades\Lattice;
use Lattice\Ui\Components\MenuItem;
use Lattice\Ui\Enums\Icon;

final class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Lattice::extend(
            'admin.sidebar.instance',
            static fn (): MenuItem => MenuItem::fromPage(ApiReferencePage::class)
                ->label(__('navigation.api'))
                ->prefix(Icon::CodeXml)
                ->can(ManagementScope::RealmsRead),
            priority: 30,
        );

        Gate::before(function (User $user, string $ability): ?bool {
            $scope = ManagementScope::tryFrom($ability);

            return $scope === null ? null : $user->hasManagementScope($scope);
        });

        Lattice::extend('app.user-menu', static fn (): MenuItem => MenuItem::make(__('navigation.admin'))
            ->href(route('admin.realms', absolute: false))
            ->prefix(Icon::Settings)
            ->visible(RequestUser::current()?->managementScopes()->isNotEmpty() ?? false));
    }
}
