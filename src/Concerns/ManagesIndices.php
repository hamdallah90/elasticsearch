<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Concerns;

use Matchory\Elasticsearch\Index;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use RuntimeException;

trait ManagesIndices
{
    /**
     * Retrieves the connection instance.
     *
     * @return ConnectionInterface
     */
    abstract public function getConnection(): ConnectionInterface;

    /**
     * Retrieves the index name.
     *
     * @return string|null
     */
    abstract public function getIndex(): string|null;

    /**
     * Creates the index of the current model instance, or the index specified
     * as the name parameter.
     *
     * @param string|null   $name
     * @param callable|null $callback
     *
     * @return array
     * @throws RuntimeException
     */
    public function createIndex(
        string|null $name = null,
        callable|null $callback = null
    ): array {
        $name = $name ?: $this->getIndex();

        if ( ! $name) {
            throw new RuntimeException('No index configured');
        }

        $index = new Index($this->getConnection(), $name);

        if ($callback) {
            $callback($index, $this);
        }

        return $index->create();
    }

    /**
     * Drops the index of the current model instance, or the index specified as
     * the name parameter.
     *
     * @param string|null $name
     *
     * @throws RuntimeException
     */
    public function dropIndex(string|null $name = null): void
    {
        $name = $name ?: $this->getIndex();

        if ( ! $name) {
            throw new RuntimeException('No index name configured');
        }

        $index = new Index($this->getConnection(), $name);
        $index->drop();
    }

    /**
     * Checks whether an index exists.
     *
     * @param string|null $name
     *
     * @return bool
     * @throws RuntimeException
     */
    public function indexExists(string|null $name = null): bool
    {
        $name = $name ?: $this->getIndex();

        if ( ! $name) {
            throw new RuntimeException('No index configured');
        }

        return (new Index($this->getConnection(), $name))
            ->exists();
    }
}
