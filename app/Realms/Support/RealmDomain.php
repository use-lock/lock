<?php

declare(strict_types=1);

namespace App\Realms\Support;

use App\Realms\Models\Realm;
use Illuminate\Validation\Rule;

final class RealmDomain
{
    /**
     * A lowercase host name of at least two labels whose last label starts
     * with a letter — which rules out IP literals, ports and paths, the things
     * a domain check must never be pointed at.
     */
    public const string PATTERN = '/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?\z/';

    /** @return list<mixed> */
    public static function rules(?Realm $ignore = null): array
    {
        return [
            'required',
            'string',
            'max:253',
            'regex:'.self::PATTERN,
            Rule::notIn([Realm::masterHost()]),
            Rule::unique('realms', 'domain')->ignore($ignore?->id),
        ];
    }
}
