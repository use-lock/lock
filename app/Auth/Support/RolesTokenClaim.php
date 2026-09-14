<?php
declare(strict_types=1);

namespace App\Auth\Support;

use App\Auth\Models\User;
use Lock\Server\Tokens\Pipeline\AccessTokenApi;
use Lock\Server\Tokens\Pipeline\AccessTokenPipeline;
use Lock\Server\Tokens\Pipeline\AuthorizationCodeEvent;
use Lock\Server\Tokens\Pipeline\TokenExchangeEvent;

/**
 * Access tokens have no claims resolver, only the issuance pipeline, which runs
 * again on every refresh so the claim follows the user's current roles.
 */
final class RolesTokenClaim
{
    public const string CLAIM = 'roles';

    /** @var list<string> */
    private const array USER_BOUND_GRANTS = ['authorization_code', 'token_exchange'];

    public static function register(AccessTokenPipeline $pipeline): void
    {
        $claim = new self;

        foreach (self::USER_BOUND_GRANTS as $grant) {
            $pipeline->register($grant, $claim(...));
        }
    }

    public function __invoke(AuthorizationCodeEvent|TokenExchangeEvent $event, AccessTokenApi $api): void
    {
        if ($event->user instanceof User) {
            $api->setAccessTokenClaim(self::CLAIM, $event->user->roleNames());
        }
    }
}
