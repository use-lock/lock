<?php
declare(strict_types=1);

namespace App\Admin\Ui\Remote;

use App\Admin\Enums\ApiResource;
use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementApi;
use App\Realms\Models\Realm;
use App\Shared\Auth\Support\RequestUser;
use DateInterval;
use Illuminate\Http\Request;
use Lattice\Core\Attributes\AsRemoteSource;
use Lattice\Core\Remote\BrowserToken;
use Lattice\Remote\RemoteSourceDefinition;
use Lock\Server\Shared\Tokens\AccessTokenMinter;
use RuntimeException;

/**
 * Mints the token the API reference's playground executes with. Nothing is
 * minted by opening the page: the browser asks for one the first time a request
 * runs, for exactly the scopes that operation declares.
 */
#[AsRemoteSource(ApiReferenceTokens::KEY, can: ManagementScope::RealmsWrite)]
final class ApiReferenceTokens extends RemoteSourceDefinition
{
    public const string KEY = 'admin.api-reference.tokens';

    /** Long enough to execute a request, short enough to be worthless if it leaks. */
    private const int LIFETIME = 120;

    public function __construct(
        private readonly ManagementApi $api,
        private readonly AccessTokenMinter $minter,
    ) {}

    public function issueBrowserToken(Request $request): BrowserToken
    {
        $user = RequestUser::of($request);

        abort_if($user === null, 403);

        $audience = $request->string('audience')->toString();
        $resource = array_find(ApiResource::cases(), fn (ApiResource $candidate): bool => $this->api->audience($candidate) === $audience);

        abort_if($resource === null, 403);

        $scopes = $this->scopes($request, $resource);

        abort_if($scopes === [], 403);

        $token = $this->minter->mint(
            userId: $user->getKey(),
            clientId: $this->consoleClientId(),
            scopeIds: $scopes,
            ttl: new DateInterval('PT'.self::LIFETIME.'S'),
            audiences: [$audience],
        );

        return new BrowserToken(
            accessToken: $token->jwt,
            tokenType: 'Bearer',
            expiresIn: self::LIFETIME,
            audience: $audience,
            scopes: $scopes,
        );
    }

    /** @return list<string> */
    private function scopes(Request $request, ApiResource $resource): array
    {
        $scopes = [];
        foreach ($request->array('scopes') as $value) {
            abort_unless(is_string($value), 403);
            $scope = ManagementScope::tryFrom($value);
            abort_if($scope === null || $scope->apiResource() !== $resource, 403);
            $scopes[] = $scope->value;
        }

        return array_values(array_unique($scopes));
    }

    private function consoleClientId(): string
    {
        $clientId = Realm::master()->clients()->firstPartyClientId;

        return $clientId ?? throw new RuntimeException('The console client is not provisioned, so no playground token can be minted.');
    }
}
