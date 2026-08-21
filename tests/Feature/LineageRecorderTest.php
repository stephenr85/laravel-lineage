<?php

use Illuminate\Support\Facades\Auth;
use Rushing\Lineage\LineageRecorder;
use Rushing\Lineage\Models\Lineage;
use Rushing\Lineage\Tests\Fixtures\FixtureArtifact;
use Rushing\Lineage\Tests\Fixtures\FixtureProducer;

beforeEach(function (): void {
    $this->recorder = app(LineageRecorder::class);
});

it('records the produced artifact as a morph', function (): void {
    $artifact = FixtureArtifact::create(['name' => 'take-1']);

    $lineage = $this->recorder->record($artifact, 'composition.render', 'comp-7', ['label' => 'Take 1']);

    expect($lineage->produced_type)->toBe($artifact->getMorphClass())
        ->and($lineage->produced_id)->toBe($artifact->getKey())
        ->and($lineage->produced->is($artifact))->toBeTrue();
});

it('keeps producer_kind an unvalidated string', function (): void {
    $artifact = FixtureArtifact::create(['name' => 'take-1']);

    // The mechanism has no vocabulary of its own: any string is a legal kind, because the closed
    // set of kinds is the seam's opinion to hold. A cast to a consumer's enum here would be the
    // downstream-autoload problem this package was split out to dissolve.
    $lineage = $this->recorder->record($artifact, 'a.kind.nobody.registered', 'k', []);

    expect($lineage->fresh()->producer_kind)->toBeString()->toBe('a.kind.nobody.registered');
});

it('records the derived-from edge as a morph, not a bare fragment id', function (): void {
    $source = FixtureArtifact::create(['name' => 'source']);
    $derived = FixtureArtifact::create(['name' => 'derived']);

    $lineage = $this->recorder->record($derived, 'transformation', 't-1', [], derivedFrom: $source);

    expect($lineage->derived_from_type)->toBe($source->getMorphClass())
        ->and($lineage->derived_from_id)->toBe($source->getKey())
        ->and($lineage->derivedFrom->is($source))->toBeTrue();
});

it('leaves the derived-from edge null for a non-deriving producer', function (): void {
    $artifact = FixtureArtifact::create(['name' => 'minted']);

    $lineage = $this->recorder->record($artifact, 'url_batch', 'b-1', []);

    expect($lineage->derived_from_type)->toBeNull()
        ->and($lineage->derived_from_id)->toBeNull()
        ->and($lineage->derivedFrom)->toBeNull();
});

it('answers the downstream query off the indexed morph', function (): void {
    $source = FixtureArtifact::create(['name' => 'source']);
    $a = FixtureArtifact::create(['name' => 'a']);
    $b = FixtureArtifact::create(['name' => 'b']);
    $unrelated = FixtureArtifact::create(['name' => 'unrelated']);

    $this->recorder->record($a, 'transformation', 't-1', [], derivedFrom: $source);
    $this->recorder->record($b, 'transformation', 't-2', [], derivedFrom: $source);
    $this->recorder->record($unrelated, 'transformation', 't-3', []);

    $downstream = Lineage::query()
        ->where('derived_from_type', $source->getMorphClass())
        ->where('derived_from_id', $source->getKey())
        ->pluck('produced_id');

    expect($downstream)->toHaveCount(2)
        ->and($downstream->all())->toEqualCanonicalizing([$a->getKey(), $b->getKey()]);
});

it('stores the snapshot as self-contained json that outlives the producer', function (): void {
    $artifact = FixtureArtifact::create(['name' => 'take-1']);
    $producer = FixtureProducer::create(['name' => 'Circuit A']);

    $lineage = $this->recorder->record(
        $artifact,
        'circuit',
        $producer->getKey(),
        ['label' => 'Circuit A', 'runId' => 'run-9'],
        producerRef: $producer,
    );

    $producer->delete();

    $reread = $lineage->fresh();
    expect($reread->snapshot)->toBe(['label' => 'Circuit A', 'runId' => 'run-9'])
        // The live reference is best-effort and is allowed to dangle; the snapshot is not.
        ->and($reread->producerRef)->toBeNull()
        ->and($reread->producer_ref_id)->toBe($producer->getKey());
});

it('captures the authenticated causer at production time', function (): void {
    $artifact = FixtureArtifact::create(['name' => 'take-1']);
    $causerId = (string) Str::uuid();
    Auth::shouldReceive('id')->andReturn($causerId);

    $lineage = $this->recorder->record($artifact, 'circuit', 'c-1', []);

    expect($lineage->causer_id)->toBe($causerId);
});

it('leaves causer null when nobody is authenticated', function (): void {
    $artifact = FixtureArtifact::create(['name' => 'take-1']);

    expect($this->recorder->record($artifact, 'circuit', 'c-1', [])->causer_id)->toBeNull();
});
