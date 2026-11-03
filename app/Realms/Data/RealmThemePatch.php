<?php

declare(strict_types=1);

namespace App\Realms\Data;

use App\Realms\Support\RealmThemeConfiguration;
use App\Shared\Data\ApiData;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Optional;

/**
 * A submission against a realm's theme. What each setting accepts is not
 * declared here: {@see RealmThemeConfiguration::apply()} holds every caller to
 * it, because a form builds this object without going through request
 * validation.
 */
final class RealmThemePatch extends ApiData
{
    /**
     * @param  array<string, mixed>|Optional  $values  JSON may carry any type, which the grammar refuses rather than a type error
     * @param  array<array-key, RealmThemeFontFace>|Optional  $fontFaces
     */
    public function __construct(
        #[SpecProperty('`system` lets each visitor choose; `light` and `dark` fix the mode for the whole realm.')]
        public Optional|string $appearance,
        #[SpecProperty('Any subset of the theme settings by name. Settings left out keep their value; `null` clears one.')]
        public Optional|array $values,
        #[SpecProperty('The font files the font stacks name. Replaces the whole list.')]
        #[DataCollectionOf(RealmThemeFontFace::class)]
        public Optional|array $fontFaces,
    ) {}
}
