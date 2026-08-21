<?php

namespace Rushing\Lineage\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FixtureProducer extends Model
{
    use HasUuids;

    protected $table = 'fixture_producers';

    protected $guarded = [];
}
