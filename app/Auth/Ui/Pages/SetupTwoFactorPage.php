<?php

declare(strict_types=1);

namespace App\Auth\Ui\Pages;

use Lattice\Core\Attributes\AsPage;
use Lock\Server\Authentication\Ui\Pages\SetupTwoFactorPage as PackageSetupTwoFactorPage;

/**
 * The server renders this page from its own controller, so the account area
 * is chosen on the page rather than on a route: its setup form posts to the
 * identity-guarded endpoints like every component on the account page.
 */
#[AsPage(endpoints: 'account')]
final class SetupTwoFactorPage extends PackageSetupTwoFactorPage {}
