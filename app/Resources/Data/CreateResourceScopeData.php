<?php
declare(strict_types=1);

namespace App\Resources\Data;

use App\Shared\Data\ApiData;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;

final class CreateResourceScopeData extends ApiData
{
    public function __construct(
        #[SpecProperty('Scope value, unique within its resource. Whitespace is not allowed.')]
        #[Max(255), Regex('/\A[A-Za-z0-9][A-Za-z0-9._:\/-]*\z/')]
        public string $value,
        #[SpecProperty('Human readable description of the scope. Null clears the description.')]
        #[Max(255)]
        public ?string $description = null,
    ) {}
}
