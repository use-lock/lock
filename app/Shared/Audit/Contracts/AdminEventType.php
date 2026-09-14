<?php
declare(strict_types=1);

namespace App\Shared\Audit\Contracts;

use App\Shared\Audit\Enums\AdminEventCategory;

/**
 * The vocabulary of the admin trail. Every domain backs it with an enum whose
 * values are dotted strings carrying the category as their first segment.
 */
interface AdminEventType
{
    public function type(): string;

    public function category(): AdminEventCategory;

    public function translationKey(): string;
}
