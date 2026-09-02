<?php

namespace Rushing\Lineage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Rushing\Lineage\Models\Lineage;

/**
 * The raw append. Every argument is a primitive or an Eloquent model, so the mechanism stays
 * consumable without the caller's producer vocabulary — a typed recorder that speaks in producer
 * enums and snapshot DTOs is the seam's job, and wraps this.
 */
class LineageRecorder
{
    /**
     * Record that $produced was produced by the given durable producer.
     *
     * @param  Model  $produced  The minted artifact.
     * @param  string  $producerKind  The producer's vocabulary key. Not validated here — see {@see Lineage}.
     * @param  string  $producerKey  The stable, durable producer id (e.g. a Circuit id, not an ephemeral run's).
     * @param  array<string, mixed>  $snapshot  Self-contained detail; must outlive the producer.
     * @param  Model|null  $producerRef  Best-effort live reference to the producing record (may later dangle).
     * @param  Model|LineageRef|null  $derivedFrom  The artifact this one derives from, when the producer
     *                                              is a transform. Accepts a {@see LineageRef} so a caller
     *                                              holding only the source's id need not load it.
     */
    public function record(
        Model $produced,
        string $producerKind,
        string $producerKey,
        array $snapshot,
        ?Model $producerRef = null,
        Model|LineageRef|null $derivedFrom = null,
    ): Lineage {
        $derivedFrom = LineageRef::from($derivedFrom);

        return Lineage::create([
            'produced_type' => $produced->getMorphClass(),
            'produced_id' => $produced->getKey(),
            'producer_kind' => $producerKind,
            'producer_key' => $producerKey,
            'derived_from_type' => $derivedFrom?->type,
            'derived_from_id' => $derivedFrom?->id,
            'causer_id' => Auth::id(),
            'snapshot' => $snapshot,
            'producer_ref_type' => $producerRef?->getMorphClass(),
            'producer_ref_id' => $producerRef?->getKey(),
            'produced_at' => now(),
        ]);
    }
}
