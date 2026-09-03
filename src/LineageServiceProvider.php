<?php

namespace Rushing\Lineage;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Rushing\Lineage\Models\Lineage;
use Rushing\PermissionCascade\Support\CascadePolicyRegistrar;

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

    public function boot(): void
    {
        // The `lineage` morph alias — the wire identifier polymorphic rows store for this model, and
        // the permission-token prefix (ADR-0118: the alias IS the prefix, so an unaliased Lineage would
        // mint `rushinglineagemodelslineage.view`). The package that OWNS the model owns its alias.
        // ADDITIVE (`Relation::morphMap`), never `enforceMorphMap`: a composing host has many models
        // on class-string morphs, and strict mode would reject every one of them.
        Relation::morphMap(['lineage' => Lineage::class]);

        // `Lineage` binds its authorization HERE, in the package that owns the model (api-surface-
        // coherence 147; the shape beam landed for `Hook`). Until this line existed the model carried
        // NO policy, and a host read that absence four ways at once: the filters sub-surface fell
        // through to any authenticated user, `show` denied everyone but a Root bypass, the write
        // pipeline denied, and the Frame nav hid the seat. One declaration, consumed by all four. The
        // `lineage.*` family is the consuming app's to seed, exactly as the DDL is.
        CascadePolicyRegistrar::register(Lineage::class);
    }
}
