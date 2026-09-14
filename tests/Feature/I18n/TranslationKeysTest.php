<?php
declare(strict_types=1);

use Illuminate\Support\Arr;

use function Tests\Helpers\globPaths;

it('keeps en and de key sets in sync', function () {
    $enFiles = collect(globPaths(lang_path('en/*.php')))->map(fn (string $path) => basename($path, '.php'));
    $deFiles = collect(globPaths(lang_path('de/*.php')))->map(fn (string $path) => basename($path, '.php'));

    expect($deFiles->all())->toEqualCanonicalizing($enFiles->all());

    foreach ($enFiles as $file) {
        $en = Arr::dot(require lang_path("en/{$file}.php"));
        $de = Arr::dot(require lang_path("de/{$file}.php"));
        expect(array_keys($de))->toEqualCanonicalizing(array_keys($en));
    }
});

it('uses nested keys for structural translation metadata', function () {
    $misalignedKeys = collect(globPaths(lang_path('en/*.php')))
        ->flatMap(function (string $path): array {
            $file = basename($path, '.php');

            return collect(array_keys(Arr::dot(require $path)))
                ->map(fn (string $key): string => "{$file}.{$key}")
                ->all();
        })
        ->filter(fn (string $key): bool => preg_match(
            '/(?:^|\.)(?:[^.]+-(?:heading|subtitle|placeholder|help-text|label)|[^.]+-confirm(?:-(?:title|description|label))?|confirm-(?:title|description|label)|(?:status|description|message)-(?:enabled|disabled|restricted))$/',
            $key,
        ) === 1)
        ->values()
        ->all();

    expect($misalignedKeys)->toBeEmpty();
});
