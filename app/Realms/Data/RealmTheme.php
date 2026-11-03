<?php

declare(strict_types=1);

namespace App\Realms\Data;

use App\Realms\Enums\RealmAppearance;
use App\Realms\Support\MapAsObjectTransformer;
use App\Realms\Support\PlainListTransformer;
use App\Shared\Data\Data;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithTransformer;

/**
 * How a realm looks. It only ever holds what a realm moves away from the
 * instance's look, so an empty theme renders exactly as if there were none.
 */
final class RealmTheme extends Data
{
    /**
     * @param  array<string, string>  $values
     * @param  list<RealmThemeFontFace>  $fontFaces
     */
    public function __construct(
        #[SpecProperty('`system` lets each visitor choose; `light` and `dark` fix the mode for the whole realm.')]
        public RealmAppearance $appearance = RealmAppearance::System,
        #[SpecProperty('The theme settings by name: Lattice tokens such as `--lt-primary` and settings such as `logo-url`.')]
        #[WithTransformer(MapAsObjectTransformer::class)]
        public array $values = [],
        #[SpecProperty('The font files the font stacks name.')]
        #[DataCollectionOf(RealmThemeFontFace::class)]
        #[WithTransformer(PlainListTransformer::class)]
        public array $fontFaces = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->appearance === RealmAppearance::System && $this->values === [] && $this->fontFaces === [];
    }

    public function value(string $name): ?string
    {
        return $this->values[$name] ?? null;
    }
}
