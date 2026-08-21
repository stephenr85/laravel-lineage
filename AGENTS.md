> You are in **rushing/laravel-lineage** — record-agnostic derivation records for Laravel (the append-only mechanism half of lineage).

An append-only provenance record: a `Lineage` row says which durable producer minted which artifact,
carrying a self-contained JSON snapshot that outlives the producer, a best-effort live producer
reference that is allowed to dangle, and an optional `derived_from` morph for transform edges. The
package ships the model plus the raw append; the consuming app supplies the artifacts and owns the
migration.

## The one load-bearing rule: this tier holds no vocabulary

`producer_kind` is a **bare string the mechanism never validates**, and `snapshot` is a plain array.
A closed enum of producer kinds, the typed snapshot DTOs it discriminates, label/link resolution and
any HTTP projection are *opinions*, and opinions belong one tier up (ADR-0127 — *the seam is
opinionated; the mechanism is not*). A typed recorder that speaks producer enums and snapshot DTOs is
the seam's to write, and wraps `LineageRecorder`.

This is not a stylistic preference. The arrangement this package replaced kept the model in
`splicewire/laravel-satellite-knowledge` while casting `producer_kind` to an enum in `splicewire/tower`
— reachable only by runtime-only FQN, with a comment explaining that the indirection existed so the
declaring package would never autoload a downstream one. A string needs no such evasion.

## Migration ownership

The package ships **no DDL**, so it stays consumable by any Laravel app — tenant, shared, or neither.
`rushing/laravel-versioning` locked the same decision for the same reason; follow it. The contract
shape the host must stand up is in `tests/TestCase.php::defineDatabaseMigrations()`, which is the
executable copy of it.

## Local dev

```bash
composer install
composer test   # pest
composer pint   # style
```
