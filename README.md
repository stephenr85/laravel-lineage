# rushing/laravel-lineage

Record-agnostic **derivation records** for Laravel — the append-only *mechanism* half of lineage.

A `Lineage` row records that an artifact was minted by a durable producer:

```php
app(LineageRecorder::class)->record(
    produced: $take,                      // the minted artifact (morph)
    producerKind: 'composition.render',   // vocabulary key — NOT validated here
    producerKey: $composition->id,        // the durable producer id, not an ephemeral run's
    snapshot: ['label' => 'Take 1'],      // self-contained; outlives the producer
    producerRef: $composition,            // best-effort live ref; may dangle
    derivedFrom: $sourceTake,             // optional transform edge
);
```

## What is deliberately absent

No producer enum, no snapshot DTOs, no controller, no migration. Those are the **seam** tier's
(ADR-0127: *the seam is opinionated; the mechanism is not*). See `AGENTS.md` for why that boundary is
load-bearing rather than tidy, and for the contract shape the host must migrate.

## Tiering

Foundation vendor — `php` + `illuminate/*` only. No `splicewire/*`, no `schemastud/*`.
