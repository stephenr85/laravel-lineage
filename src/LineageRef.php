<?php

namespace Rushing\Lineage;

use Illuminate\Database\Eloquent\Model;

/**
 * A morph target expressed without loading it.
 *
 * `produced` and `producerRef` are always models in hand at record time, so they are passed as
 * models. A derives-from edge often is not: the producer knows the source's id because it just
 * read it, and loading the row purely to record the edge would cost a query and — worse — yield
 * null for a source that has since been deleted, silently dropping the edge that outliving
 * deletion is the whole point of.
 */
class LineageRef
{
    private function __construct(
        public string $type,
        public string $id,
    ) {}

    /** From a model in hand. */
    public static function to(Model $model): self
    {
        return new self($model->getMorphClass(), (string) $model->getKey());
    }

    /**
     * From a morph type and key already known. `$type` should be whatever `getMorphClass()`
     * would yield — the morph-map alias where one is registered, the FQCN otherwise.
     */
    public static function of(string $type, string $id): self
    {
        return new self($type, $id);
    }

    public static function from(Model|self|null $value): ?self
    {
        return $value instanceof Model ? self::to($value) : $value;
    }
}
