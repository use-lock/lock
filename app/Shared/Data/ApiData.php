<?php

declare(strict_types=1);

namespace App\Shared\Data;

use Spatie\LaravelData\DataPipeline;
use Spatie\LaravelData\DataPipes\AuthorizedDataPipe;
use Spatie\LaravelData\DataPipes\CastPropertiesDataPipe;
use Spatie\LaravelData\DataPipes\DefaultValuesDataPipe;
use Spatie\LaravelData\DataPipes\InjectPropertyValuesPipe;
use Spatie\LaravelData\DataPipes\MapPropertiesDataPipe;
use Spatie\LaravelData\DataPipes\ValidatePropertiesDataPipe;

/**
 * Base class for every data object that is created from an HTTP request.
 * Rebuilds the default pipeline to reject unknown payload keys right after
 * name mapping — before validation and casting consume the payload.
 */
abstract class ApiData extends Data
{
    public static function pipeline(): DataPipeline
    {
        return DataPipeline::create()
            ->into(static::class)
            ->through(AuthorizedDataPipe::class)
            ->through(MapPropertiesDataPipe::class)
            ->through(RejectUnknownPropertiesDataPipe::class)
            ->through(InjectPropertyValuesPipe::class)
            ->through(ValidatePropertiesDataPipe::class)
            ->through(DefaultValuesDataPipe::class)
            ->through(CastPropertiesDataPipe::class);
    }
}
