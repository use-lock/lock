<?php
declare(strict_types=1);

namespace App\Shared\Clients\Contracts;

use App\Admin\Enums\BootstrapOutcome;
use App\Realms\Models\Realm;
use SensitiveParameter;

interface ProvisionsConsoleClient
{
    /**
     * @param  list<string>  $redirectUris
     * @param  list<string>  $postLogoutRedirectUris
     */
    public function handle(
        Realm $realm,
        string $name,
        string $clientId,
        #[SensitiveParameter] string $clientSecret,
        bool $trusted,
        array $redirectUris,
        array $postLogoutRedirectUris,
    ): BootstrapOutcome;
}
