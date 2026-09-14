<?php
declare(strict_types=1);

namespace App\Realms\Http\Api\V1\Controllers;

use App\Realms\Actions\CreateRealm;
use App\Realms\Actions\DeleteRealm;
use App\Realms\Actions\UpdateRealm;
use App\Realms\Data\CreateRealmData;
use App\Realms\Data\UpdateRealmData;
use App\Realms\Http\Api\V1\Resources\RealmData;
use App\Realms\Models\Realm;
use Bambamboole\Spectacular\QueryBuilder;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\PaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final readonly class RealmController
{
    public function __construct(
        private CreateRealm $createRealm,
        private UpdateRealm $updateRealm,
        private DeleteRealm $deleteRealm,
    ) {}

    /**
     * List realms
     *
     * Every realm this instance serves, the administration realm included.
     *
     * @return PaginatedDataCollection<array-key, RealmData>
     */
    public function index(): PaginatedDataCollection
    {
        $realms = QueryBuilder::for(Realm::class)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::exact('slug'),
                AllowedFilter::exact('domain'),
                AllowedFilter::exact('domain_status'),
            )
            ->allowedSorts('name', 'slug', 'created_at')
            ->defaultSort('name')
            ->apiPaginate();

        return RealmData::collect($realms, PaginatedDataCollection::class);
    }

    /**
     * Show a realm
     */
    public function show(Realm $realm): RealmData
    {
        return RealmData::fromRealm($realm);
    }

    /**
     * Create a realm
     *
     * The realm gets its own signing keypair, and its domain is checked right
     * away. A domain that does not reach this instance yet is reported through
     * `domain_status` rather than refused — DNS and the proxy usually follow.
     * Settings left out of `settings` follow the instance defaults.
     */
    #[IgnoreResponse(200)]
    #[Response(201, type: RealmData::class)]
    public function store(CreateRealmData $data): SymfonyResponse
    {
        $realm = $this->reportingSettingErrors(fn (): Realm => $this->createRealm->handle($data));

        return RealmData::fromRealm($realm->refresh())->toResponse(request())->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Update a realm
     *
     * A realm's slug is immutable. Settings left out of `settings` keep
     * their current value. Re-pointing the domain changes the issuer of every
     * token the realm mints and restarts the domain check.
     */
    public function update(UpdateRealmData $data, Realm $realm): RealmData
    {
        $realm = $this->reportingSettingErrors(fn (): Realm => $this->updateRealm->handle($realm, $data));

        return RealmData::fromRealm($realm->refresh());
    }

    /**
     * Both actions validate the submission merged into a whole configuration —
     * the realm's current values, or the defaults for one being created — so
     * they report a setting under its own name. On the wire the settings live
     * under `settings`, and an error has to point there.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $write
     * @return TReturn
     */
    private function reportingSettingErrors(callable $write): mixed
    {
        try {
            return $write();
        } catch (ValidationException $exception) {
            $errors = [];

            foreach ($exception->errors() as $key => $messages) {
                $errors[in_array($key, ['name', 'slug', 'domain'], true) ? $key : 'settings.'.$key] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Delete a realm
     *
     * Purges the realm's users, roles, clients, tokens and keys, and frees its
     * slug. The administration realm cannot be deleted.
     */
    public function destroy(Realm $realm): SymfonyResponse
    {
        abort_if($realm->isMaster(), SymfonyResponse::HTTP_CONFLICT, 'The administration realm cannot be deleted.');

        $this->deleteRealm->handle($realm);

        return response()->noContent();
    }
}
