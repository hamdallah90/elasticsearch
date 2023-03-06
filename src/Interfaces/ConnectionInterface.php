<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Interfaces;

use Elastic\Elasticsearch\Client;
use Matchory\Elasticsearch\Builder;
use Psr\SimpleCache\CacheInterface;

/**
 * Connection Interface
 *
 * @template T of \Matchory\Elasticsearch\Model
 * @package  Matchory\Elasticsearch\Interfaces
 */
interface ConnectionInterface
{
    /**
     * Retrieves the cache instance, if configured.
     *
     * @return CacheInterface|null
     */
    public function getCache(): CacheInterface|null;

    /**
     * Retrieves the Elasticsearch client.
     *
     * @return Client
     */
    public function getClient(): Client;

    /**
     * Create a new query on the given index.
     *
     * @param string $index Name of the index to query.
     *
     * @return Builder<T> Query builder instance.
     */
    public function index(string $index): Builder;

    /**
     * Adds a document to the index using the specified parameters.
     *
     * @param array       $parameters Parameters to index the document with
     * @param string|null $index      Index to insert the document into.
     *                                Defaults to the default index of the
     *                                connection.
     *
     * @return array{
     *     _shards: array{
     *         total: int,
     *         failed: int,
     *         successful: int
     *     },
     *     _index: string,
     *     _id: string,
     *     _version: int,
     *     _seq_no: int,
     *     _primary_term: int,
     *     result: string
     * }
     */
    public function insert(
        array $parameters,
        string|null $index = null
    ): array;

    /**
     * Creates a new Elasticsearch query
     *
     * @return Builder<T>
     */
    public function newQuery(): Builder;
}
