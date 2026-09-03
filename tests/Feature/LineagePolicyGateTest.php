<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Rushing\Lineage\Models\Lineage;
use Rushing\PermissionCascade\PermissionCascadeServiceProvider;
use Rushing\PermissionCascade\Policies\ConfiguredModelPolicy;
use Rushing\PermissionCascade\Support\PermissionNamer;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\PermissionServiceProvider;
use Spatie\Permission\Traits\HasRoles;

/**
 * api-surface-coherence 147 — the `lineages` particle resource (declared one tier up, in
 * `splicewire/laravel-beam-lineage`) backs THIS package's `Lineage`, which carried no policy. A host
 * read that absence four ways at once (135): the `lineage/filters/*` sub-surface fell through to any
 * authenticated user, `show` denied everyone but a Root bypass, writes denied, the Frame nav hid the
 * seat. The package that owns the model owns its policy binding, so `#[UseCascadePolicy]` lives here
 * and `LineageServiceProvider` registers it — one declaration, consumed by all four — under the
 * `lineage` alias the same provider now owns (ADR-0118: the alias IS the token prefix).
 *
 * Gate CLOSED: no `Gate::before(fn () => true)`, the control probe first, spatie's plane booted so a
 * holder is a principal the cascade can admit.
 */
beforeEach(function () {
    Schema::create('users', function (Blueprint $t): void {
        $t->id();
        $t->string('email')->nullable();
    });

    lineagePolicySpatieSchema();

    config()->set('auth.providers.users.model', LineagePolicyGateUser::class);
});

function lineagePolicySpatieSchema(): void
{
    Schema::create('permissions', function (Blueprint $t): void {
        $t->id();
        $t->string('name');
        $t->string('guard_name');
        $t->timestamps();
        $t->unique(['name', 'guard_name']);
    });

    Schema::create('roles', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('team_id')->nullable();
        $t->string('name');
        $t->string('guard_name');
        $t->timestamps();
    });

    Schema::create('model_has_permissions', function (Blueprint $t): void {
        $t->unsignedBigInteger('permission_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->unsignedBigInteger('team_id')->nullable();
        $t->index(['model_id', 'model_type']);
    });

    Schema::create('model_has_roles', function (Blueprint $t): void {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->unsignedBigInteger('team_id')->nullable();
        $t->index(['model_id', 'model_type']);
    });

    Schema::create('role_has_permissions', function (Blueprint $t): void {
        $t->unsignedBigInteger('permission_id');
        $t->unsignedBigInteger('role_id');
    });
}

function lineagePolicyStranger(): LineagePolicyGateUser
{
    return LineagePolicyGateUser::create(['email' => 'm'.mt_rand().'@lineage.test']);
}

function lineagePolicyHolder(string ...$abilities): LineagePolicyGateUser
{
    $user = lineagePolicyStranger();
    foreach ($abilities as $ability) {
        $user->givePermissionTo(Permission::findOrCreate($ability, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function lineagePolicyRow(): Lineage
{
    return Lineage::create([
        'produced_type' => 'fixture_artifact',
        'produced_id' => (string) \Illuminate\Support\Str::uuid(),
        'producer_kind' => 'probe',
        'producer_key' => 'probe',
        'snapshot' => ['probe' => true],
        'produced_at' => now(),
    ]);
}

it('CONTROL: the gate is closed for a stranger', function () {
    expect(Gate::forUser(lineagePolicyStranger())->allows('probe-nonexistent-ability'))->toBeFalse();
});

it('CONTROL: the cascade provider is booted, not auto-resolved fresh per call', function () {
    expect(app(PermissionNamer::class))->toBe(app(PermissionNamer::class));
});

it('binds a cascade policy that answers viewAny on Lineage', function () {
    $policy = Gate::getPolicyFor(Lineage::class);

    expect($policy)->toBeInstanceOf(ConfiguredModelPolicy::class)
        ->and(method_exists($policy, 'viewAny'))->toBeTrue();
});

it('mints the token prefix off the lineage alias, never the FQCN (ADR-0118)', function () {
    expect(app(PermissionNamer::class)->assemble(Lineage::class, 'view'))->toBe('lineage.view');
});

it('denies a stranger every ability on a lineage row', function () {
    $gate = Gate::forUser(lineagePolicyStranger());
    $row = lineagePolicyRow();

    expect($gate->allows('viewAny', Lineage::class))->toBeFalse()
        ->and($gate->allows('view', $row))->toBeFalse()
        ->and($gate->allows('delete', $row))->toBeFalse();
});

it('admits a holder of lineage.view, gate closed, and only for view', function () {
    $gate = Gate::forUser(lineagePolicyHolder('lineage.view'));
    $row = lineagePolicyRow();

    expect($gate->allows('probe-nonexistent-ability'))->toBeFalse()
        ->and($gate->allows('viewAny', Lineage::class))->toBeTrue()
        ->and($gate->allows('view', $row))->toBeTrue()
        ->and($gate->allows('delete', $row))->toBeFalse();
});

class LineagePolicyGateUser extends AuthUser
{
    use HasRoles;

    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = [];
}
