<?php
declare(strict_types=1);

namespace App\Clients\Services;

use App\Realms\Models\Realm;
use Illuminate\Validation\ValidationException;
use Lock\Server\Shared\Realms\RealmAudiences;

/**
 * A client is only allowed audiences its realm answers to: the issuer and its
 * registered resources, spelled as {@see RealmAudiences::identifier()} resolves
 * them — which is what the client sends as `resource` and what lands in `aud`.
 * Entries the client already holds are kept as they are, so deleting a
 * resource does not block every later edit of the clients that named it.
 */
final readonly class RealmAudienceAllowlist
{
    public function __construct(private RealmAudiences $audiences) {}

    /**
     * @param  list<string>  $requested
     * @param  list<string>  $held
     *
     * @throws ValidationException
     */
    public function assertAllowed(Realm $realm, array $requested, array $held = []): void
    {
        $unknown = array_values(array_diff(
            $requested,
            $held,
            $realm->runAsCurrent(fn (): array => $this->audiences->all()),
        ));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'allowed_audiences' => array_map(
                    fn (string $audience): string => __('clients.validation.unknown-audience', ['audience' => $audience]),
                    $unknown,
                ),
            ]);
        }
    }
}
