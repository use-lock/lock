<?php

declare(strict_types=1);

namespace App\Shared\Data;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\Contracts\PropertyMorphableData;
use Spatie\LaravelData\DataPipes\DataPipe;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataClass;
use Spatie\LaravelData\Support\DataConfig;

/**
 * Rejects request payload keys that do not map to a data property, so typos
 * and attempts to write read-only fields fail loudly with a 422 instead of
 * being silently discarded. Only request payloads are checked — programmatic
 * Data::from([...]) calls stay permissive. Nested data objects are created
 * from plain arrays further down the pipeline, so nesting is checked here
 * recursively while the original request payload is still at hand.
 */
final readonly class RejectUnknownPropertiesDataPipe implements DataPipe
{
    public function __construct(private DataConfig $dataConfig) {}

    /**
     * @param  array<array-key, mixed>  $properties
     * @param  CreationContext<BaseData<mixed, mixed, array-key>>  $creationContext
     * @return array<array-key, mixed>
     */
    public function handle(mixed $payload, DataClass $class, array $properties, CreationContext $creationContext): array
    {
        if (! $payload instanceof Request) {
            return $properties;
        }

        $unknownKeys = $this->unknownKeys($class, $properties, '');

        if ($unknownKeys !== []) {
            throw ValidationException::withMessages(
                array_fill_keys($unknownKeys, [__('common.validation.property.unknown')]),
            );
        }

        return $properties;
    }

    /**
     * @param  array<array-key, mixed>  $properties
     * @return list<string>
     */
    private function unknownKeys(DataClass $class, array $properties, string $path): array
    {
        $allowedKeys = [];

        foreach ($class->properties as $property) {
            $allowedKeys[$property->name] = true;

            if ($property->inputMappedName !== null) {
                $allowedKeys[$property->inputMappedName] = true;
            }
        }

        $unknownKeys = [];

        foreach (array_keys($properties) as $key) {
            if (! isset($allowedKeys[(string) $key])) {
                $unknownKeys[] = $path.$key;
            }
        }

        foreach ($class->properties as $property) {
            if ($property->type->dataClass === null) {
                continue;
            }

            $value = $properties[$property->name] ?? $properties[$property->inputMappedName ?? ''] ?? null;

            if (! is_array($value)) {
                continue;
            }

            $nestedClass = $this->dataConfig->getDataClass($property->type->dataClass);
            $inputName = $property->inputMappedName ?? $property->name;

            if ($property->type->kind->isDataObject()) {
                $unknownKeys = [...$unknownKeys, ...$this->unknownKeys($this->morphed($nestedClass, $value), $value, "{$path}{$inputName}.")];

                continue;
            }

            foreach ($value as $index => $item) {
                if (is_array($item)) {
                    $unknownKeys = [...$unknownKeys, ...$this->unknownKeys($this->morphed($nestedClass, $item), $item, "{$path}{$inputName}.{$index}.")];
                }
            }
        }

        return $unknownKeys;
    }

    /**
     * A property-morphable abstract only declares the discriminator — the
     * allowed keys live on the concrete class the payload morphs into.
     *
     * @param  array<array-key, mixed>  $value
     */
    private function morphed(DataClass $class, array $value): DataClass
    {
        if (! $class->isAbstract || ! $class->propertyMorphable) {
            return $class;
        }

        $abstract = $class->name;

        if (! is_a($abstract, PropertyMorphableData::class, true)) {
            return $class;
        }

        $concrete = $abstract::morph($value);

        return $concrete === null ? $class : $this->dataConfig->getDataClass($concrete);
    }
}
