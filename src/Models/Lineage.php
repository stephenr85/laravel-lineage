<?php

namespace Rushing\Lineage\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Rushing\PermissionCascade\Attributes\UseCascadePolicy;

/**
 * An append-only derivation record: `$produced` was minted by the durable producer
 * identified by (`producer_kind`, `producer_key`), optionally deriving from `$derivedFrom`.
 *
 * `producer_kind` is a **bare string this package never validates**. A closed enum of producer
 * kinds, the typed snapshot DTOs it discriminates, and any HTTP projection of them are opinions,
 * and opinions belong one tier up (ADR-0127: *the seam is opinionated; the mechanism is not*).
 * That is also why the cast is absent rather than pointing at a consumer's enum — the previous
 * arrangement reached `Splicewire\Tower\Enums\ProducerKind` by runtime-only FQN precisely so the
 * declaring package would not autoload a downstream one. A string needs no such evasion.
 *
 * `snapshot` is typed, self-contained detail captured at production time; it survives the
 * producer being deleted or pruned, which `producerRef` — a best-effort live reference that may
 * dangle — deliberately does not.
 *
 * Migration ownership: this package ships the model, never the DDL, so it stays consumable by any
 * Laravel app, tenant or not. See `rushing/laravel-versioning` for the same arrangement.
 *
 * Authorization IS shipped, because a policy is a fact about the model and the package that owns the
 * model owns its binding (api-surface-coherence 147; the shape beam landed for `Hook`).
 * `#[UseCascadePolicy]` gives `Gate::getPolicyFor(Lineage::class)` a real answer — the `lineage.*`
 * family, minted under the `lineage` alias {@see \Rushing\Lineage\LineageServiceProvider} owns
 * (ADR-0118) — which is what a consumer's filters sub-surface, `show`, write pipeline and nav all
 * read. Unconditional per ability, as an append-only record wants; the seeding of the family is the
 * consuming app's, like its DDL.
 */
#[UseCascadePolicy]
class Lineage extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'snapshot' => 'array',
        'produced_at' => 'datetime',
    ];

    /** The minted artifact. */
    public function produced(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The artifact this one derives from — the reverse-traversal edge.
     *
     * Promoted out of the `snapshot` JSON into indexed columns so the downstream query is an
     * index seek rather than a JSON scan (ADR-0107). It began life as a bare `source_fragment_id`
     * uuid because Fragment was the only contributor; the morph is that fossil corrected, not a
     * new capability.
     */
    public function derivedFrom(): MorphTo
    {
        return $this->morphTo();
    }

    /** Best-effort live reference to the producing record. May dangle. */
    public function producerRef(): MorphTo
    {
        return $this->morphTo();
    }

    /** The triggering user, captured at production time because it is unrecoverable later. */
    public function causer(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'causer_id');
    }
}
