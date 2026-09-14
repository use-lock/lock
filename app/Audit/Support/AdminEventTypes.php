<?php
declare(strict_types=1);

namespace App\Audit\Support;

use App\Auth\Enums\UserAdminEvent;
use App\Clients\Enums\ClientAdminEvent;
use App\Realms\Enums\RealmAdminEvent;
use App\Resources\Enums\ResourceAdminEvent;
use App\Roles\Enums\RoleAdminEvent;
use App\Shared\Audit\Contracts\AdminEventType;
use App\Shared\Audit\Enums\AdminEventCategory;
use BackedEnum;

/**
 * The one place that knows every vocabulary of the admin trail, so a filter can
 * offer the types of a category without a table listing enums by hand.
 */
final class AdminEventTypes
{
    /** @var array<string, class-string<AdminEventType&BackedEnum>> */
    private const array ENUMS = [
        AdminEventCategory::Realm->value => RealmAdminEvent::class,
        AdminEventCategory::User->value => UserAdminEvent::class,
        AdminEventCategory::Client->value => ClientAdminEvent::class,
        AdminEventCategory::Role->value => RoleAdminEvent::class,
        AdminEventCategory::Resource->value => ResourceAdminEvent::class,
    ];

    /**
     * @return array<string, string>
     */
    public static function options(AdminEventCategory ...$categories): array
    {
        $options = [];

        foreach (self::casesOf(...$categories) as $case) {
            $options[$case->type()] = __($case->translationKey());
        }

        return $options;
    }

    /**
     * @return list<AdminEventType>
     */
    public static function casesOf(AdminEventCategory ...$categories): array
    {
        $wanted = $categories === [] ? AdminEventCategory::cases() : $categories;
        $cases = [];

        foreach ($wanted as $category) {
            $cases = [...$cases, ...self::ENUMS[$category->value]::cases()];
        }

        return $cases;
    }
}
