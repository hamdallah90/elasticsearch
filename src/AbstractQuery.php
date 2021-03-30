<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonException;
use JsonSerializable;

use function assert;
use function json_encode;

/**
 * Abstract Query
 *
 * Base query to be extended by reusable query classes. This is most useful for
 * complex query used throughout different parts of an application.
 * All queries must implement the {@see AbstractQuery::compile()} method, which
 * receives a builder instance and may set the query logic on it.
 *
 * @bundle   Matchory\Elasticsearch
 * @template T of Builder
 */
abstract class AbstractQuery implements Arrayable, JsonSerializable, Jsonable
{
    /**
     * @var T|null
     */
    private Builder|null $builder = null;

    /**
     * @return T
     */
    public function getBuilder(): Builder
    {
        assert($this->builder instanceof Builder, sprintf(
            'No builder instance set on query %s: Queries should not ' .
            'be initialized manually but via the builder itself.',
            static::class
        ));

        return clone $this->builder;
    }

    /**
     * @param T $builder
     *
     * @internal
     */
    public function setBuilder(Builder $builder): void
    {
        $this->builder = $builder;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        return $this
            ->compile($this->getBuilder())
            ->toArray();
    }

    /**
     * @param int $options
     *
     * @return bool|string
     * @throws JsonException
     */
    public function toJson($options = 0): bool|string
    {
        return json_encode($this, JSON_THROW_ON_ERROR | $options);
    }

    /**
     * Compiles the query.
     *
     * @param T $builder
     *
     * @return T
     */
    abstract protected function compile(Builder $builder): Builder;
}
