<?php

namespace Rushing\Lineage\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\Lineage\LineageServiceProvider;
use Rushing\PermissionCascade\PermissionCascadeServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LineageServiceProvider::class,
            // `Lineage` carries `#[UseCascadePolicy]` (api-surface-coherence 147), and the cascade's
            // `PermissionNamer` is a singleton only when its provider boots — testbench does not
            // auto-discover, and an unbooted provider leaves it auto-resolvable and fresh per call
            // (`LineagePolicyGateTest`'s identity control). spatie's provider is the permission plane
            // the cascade's `$user->can()` rung resolves against.
            PermissionCascadeServiceProvider::class,
            PermissionServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // The cascade forces spatie into teams mode unless the host opts out; this harness has no
        // team, and a null `team_id` fails spatie's composite key on every grant.
        $app['config']->set('permission-cascade.manage_spatie_teams', false);
    }

    /**
     * The package ships ZERO migration by design — the consuming app owns the DDL — so the harness
     * stands up its own `lineages` table in the contract shape, plus two artifact tables to play the
     * produced / derived-from ends of the morphs.
     */
    protected function defineDatabaseMigrations(): void
    {
        Schema::create('lineages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('produced_type');
            $table->uuid('produced_id');
            $table->string('producer_kind');
            $table->string('producer_key');
            $table->string('derived_from_type')->nullable();
            $table->uuid('derived_from_id')->nullable();
            $table->uuid('causer_id')->nullable();
            $table->json('snapshot');
            $table->string('producer_ref_type')->nullable();
            $table->uuid('producer_ref_id')->nullable();
            $table->timestamp('produced_at');
            $table->timestamps();

            $table->index(['produced_type', 'produced_id']);
            $table->index(['producer_kind', 'producer_key']);
            $table->index(['derived_from_type', 'derived_from_id']);
        });

        Schema::create('fixture_artifacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('fixture_producers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }
}
