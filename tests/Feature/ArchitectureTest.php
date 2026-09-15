<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Admin\Enums\ApiResource;
use App\Admin\Enums\BootstrapOutcome;
use App\Admin\Enums\ManagementScope;
use App\Admin\ManagementApi;
use App\Admin\ManagementRoles;
use App\Auth\Enums\UserAdminEvent;
use App\Auth\Models\User;
use App\Clients\Enums\ClientAdminEvent;
use App\Realms\Enums\RealmAdminEvent;
use App\Realms\Models\Realm;
use App\Resources\Enums\ResourceAdminEvent;
use App\Resources\Models\Resource;
use App\Resources\Models\ResourceScope;
use App\Roles\Enums\RoleAdminEvent;
use App\Roles\Models\Role;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PHPUnit\Framework\ExpectationFailedException;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Tests\Fixtures\Architecture\LeakyIdentity;

use function Tests\Helpers\globPaths;

/**
 * Every child of a module except its Ui slice and its service provider, derived
 * from what exists so a new slice or root class is covered without editing
 * this file.
 *
 * @return array<int, string>
 */
function architectureLayerNamespaces(): array
{
    $appPath = dirname(__DIR__, 2).'/app';

    return collect(globPaths("{$appPath}/*/*"))
        ->reject(fn (string $path): bool => basename($path) === 'Ui' || str_ends_with($path, 'ServiceProvider.php'))
        ->map(fn (string $path): string => 'App\\'.str_replace(['/', '.php'], ['\\', ''], Str::after($path, $appPath.'/')))
        ->sort()
        ->values()
        ->all();
}

/**
 * @return array<int, string>
 */
function architectureUiNamespaces(): array
{
    $appPath = dirname(__DIR__, 2).'/app';

    return collect(globPaths("{$appPath}/*/Ui", GLOB_ONLYDIR))
        ->map(fn (string $path): string => 'App\\'.str_replace('/', '\\', Str::after($path, $appPath.'/')))
        ->sort()
        ->values()
        ->all();
}

/**
 * pest-arch silently matches nothing when the target is a bare top-level vendor
 * namespace ('Lattice'), so the forbidden targets are the concrete multi-segment
 * namespaces derived from the installed lattice-php packages.
 *
 * @return array<int, string>
 */
function architectureLatticeNamespaces(): array
{
    $targets = [];

    foreach (globPaths(dirname(__DIR__, 2).'/vendor/lattice-php/*/composer.json') as $manifest) {
        $package = json_decode((string) file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
        $autoload = is_array($package) && is_array($package['autoload']['psr-4'] ?? null) ? $package['autoload']['psr-4'] : [];

        foreach ($autoload as $prefix => $sourcePath) {
            $prefix = rtrim((string) $prefix, '\\');

            if ($prefix !== 'Lattice') {
                $targets[] = $prefix;

                continue;
            }

            $sourceDir = dirname($manifest).'/'.trim((string) $sourcePath, '/');

            foreach (globPaths("{$sourceDir}/*", GLOB_ONLYDIR) as $directory) {
                $targets[] = 'Lattice\\'.basename($directory);
            }

            foreach (globPaths("{$sourceDir}/[A-Z]*.php") as $file) {
                $targets[] = 'Lattice\\'.basename($file, '.php');
            }
        }
    }

    return collect($targets)->unique()->sort()->values()->all();
}

/**
 * A model extending a vendor model inherits the vendor's key strategy and is
 * skipped.
 *
 * @return list<class-string<Model>>
 */
function architectureDomainModels(): array
{
    $appPath = dirname(__DIR__, 2).'/app';
    $models = [];

    foreach ([...globPaths("{$appPath}/*/Models/*.php"), ...globPaths("{$appPath}/Shared/*/Models/*.php")] as $file) {
        $class = 'App\\'.str_replace('/', '\\', substr($file, strlen($appPath) + 1, -4));

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);

        if (! $reflection->isAbstract() && ! architectureExtendsVendorModel($reflection)) {
            $models[] = $class;
        }
    }

    sort($models);

    return $models;
}

/**
 * @return array<int, string>
 */
function architectureDomains(): array
{
    $appPath = dirname(__DIR__, 2).'/app';

    return collect(globPaths("{$appPath}/*", GLOB_ONLYDIR))
        ->map(fn (string $path): string => basename($path))
        ->reject(fn (string $domain): bool => $domain === 'Shared')
        ->sort()
        ->values()
        ->all();
}

/** @return list<class-string> */
function architecturePublicTypes(): array
{
    return [
        User::class,
        Realm::class,
        Role::class,
        Resource::class,
        ResourceScope::class,
        ManagementApi::class,
        ApiResource::class,
        ManagementRoles::class,
        ManagementScope::class,
        BootstrapOutcome::class,
        UserAdminEvent::class,
        ClientAdminEvent::class,
        RealmAdminEvent::class,
        ResourceAdminEvent::class,
        RoleAdminEvent::class,
    ];
}

/** @return list<string> */
function architectureForbiddenDependencies(string $source): array
{
    $targets = [];

    foreach (architectureDomains() as $domain) {
        if ($domain === $source) {
            continue;
        }

        foreach (Finder::create()->files()->in(dirname(__DIR__, 2).'/app/'.$domain)->name('*.php') as $file) {
            $class = 'App\\'.$domain.'\\'.str_replace('/', '\\', substr($file->getRelativePathname(), 0, -4));

            if (! in_array($class, architecturePublicTypes(), true)) {
                $targets[] = $class;
            }
        }
    }

    return $targets;
}

/**
 * @param  ReflectionClass<Model>  $class
 */
function architectureExtendsVendorModel(ReflectionClass $class): bool
{
    for ($parent = $class->getParentClass(); $parent !== false && $parent->getName() !== Model::class; $parent = $parent->getParentClass()) {
        if (! str_starts_with($parent->getName(), 'App\\') && ! str_starts_with($parent->getName(), 'Illuminate\\')) {
            return true;
        }
    }

    return false;
}

it('finds the namespaces its layer rules check', function () {
    expect(architectureLayerNamespaces())->not->toBeEmpty()
        ->and(architectureUiNamespaces())->not->toBeEmpty()
        ->and(architectureLatticeNamespaces())->not->toBeEmpty()
        ->and(architectureDomains())->not->toBeEmpty();
});

// One arch() per source namespace: `expect([A, B])->not->toUse(X)` passes as
// soon as ANY source lacks the dependency (pest-arch inverts "every source uses
// X"), so an array of sources silently checks nothing.
foreach (architectureLayerNamespaces() as $namespace) {
    arch("{$namespace} does not depend on the lattice ui layer")
        ->expect($namespace)
        ->not->toUse(architectureUiNamespaces());

    arch("{$namespace} does not depend on lattice")
        ->expect($namespace)
        ->not->toUse(architectureLatticeNamespaces());
}

foreach ([...architectureDomains(), 'Shared'] as $domain) {
    arch("App\\{$domain} reaches other domains only through their public types")
        ->expect("App\\{$domain}")
        ->not->toUse(architectureForbiddenDependencies($domain));
}

it('detects a model reaching into another domain implementation', function () {
    expect(function (): void {
        expect(LeakyIdentity::class)
            ->not->toUse(architectureForbiddenDependencies('Auth'));
    })->toThrow(ExpectationFailedException::class);
});

it('uses uuid primary keys on all domain models', function () {
    $models = architectureDomainModels();

    expect($models)->not->toBeEmpty();

    foreach ($models as $model) {
        expect(class_uses_recursive($model))
            ->toContain(HasUuids::class);
    }
});

it('keeps every scope value a kebab-cased area with a read or write verb', function () {
    foreach (ManagementScope::values() as $value) {
        expect($value)->toMatch('/^[a-z][a-z-]*:[a-z][a-z-]*$/')
            ->and(Str::afterLast($value, ':'))->toBeIn(['read', 'write']);
    }
});
