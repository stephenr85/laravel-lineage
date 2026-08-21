<?php

namespace Rushing\Lineage\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FixtureArtifact extends Model
{
    use HasUuids;

    protected $table = 'fixture_artifacts';

    protected $guarded = [];
}
