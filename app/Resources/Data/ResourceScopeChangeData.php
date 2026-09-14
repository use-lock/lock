<?php
declare(strict_types=1);

namespace App\Resources\Data;

use App\Shared\Data\ApiData;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Optional;

final class ResourceScopeChangeData extends ApiData
{
    public function __construct(
        #[SpecProperty('Scope name, unique within its resource. An existing value updates that scope; a new value creates one.')]
        #[Max(255), Regex('/\A[A-Za-z0-9][A-Za-z0-9._:\/-]*\z/')]
        public string $value,
        #[SpecProperty('Human readable description. Omit to keep the current description; null clears it.')]
        #[Max(255)]
        public Optional|string|null $description = new Optional,
        #[SpecProperty('Set to true to delete the scope named by value.')]
        public bool $delete = false,
    ) {}
}
