<?php

namespace Rushing\Lineage;

use Illuminate\Support\ServiceProvider;

/**
 * Package service provider for the record-agnostic derivation-record mechanism.
 *
 * There is deliberately very little here. The mechanism is a model plus an append; it binds no
 * port, publishes no config, and — following `rushing/laravel-versioning`'s locked decision —
 * ships no migration, so the `lineages` table DDL stays the consuming app's to own (tenant,
 * shared, or neither). Producer vocabulary, snapshot DTOs and HTTP projections live in the seam
 * tier and register themselves there.
 */
class LineageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LineageRecorder::class);
    }
}
