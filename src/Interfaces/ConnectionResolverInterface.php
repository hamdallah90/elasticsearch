<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Interfaces;

/**
 * Connection Resolver Interface
 *
 * @template T of \Matchory\Elasticsearch\Model
 * @bundle   Matchory\Elasticsearch
 */
interface ConnectionResolverInterface
{
    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnection(): string;

    /**
     * Set the default connection name.
     *
     * @param string $name
     *
     * @return void
     */
    public function setDefaultConnection(string $name): void;

    /**
     * Get a database connection instance.
     *
     * @param string|null $name
     *
     * @return ConnectionInterface<T>
     */
    public function connection(string|null $name = null): ConnectionInterface;
}
