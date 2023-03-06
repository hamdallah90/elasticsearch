<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch;

use BadMethodCallException;
use Elastic\Elasticsearch\Client;
use Illuminate\Support\Traits\ForwardsCalls;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Psr\SimpleCache\CacheInterface;

use function tap;

/**
 * Connection
 *
 * @template T of \Matchory\Elasticsearch\Model
 * @implements ConnectionInterface<T>
 * @package  Matchory\Elasticsearch
 */
class Connection implements ConnectionInterface
{
    use ForwardsCalls;

    private const DEFAULT_LOGGER_NAME = 'elasticsearch';

    /**
     * Creates a new connection
     *
     * @param Client              $client Elasticsearch client instance used for this connection.
     * @param CacheInterface|null $cache  Cache instance to be used for this connection. In Laravel applications, this
     *                                    will be an instance of the Cache Repository, which is the same as the
     *                                    instance returned from the Cache facade.
     * @param string|null         $index
     */
    public function __construct(
        private readonly Client $client,
        private readonly CacheInterface|null $cache = null,
        private readonly string|null $index = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCache(): CacheInterface|null
    {
        return $this->cache;
    }

    /**
     * @inheritDoc
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @inheritDoc
     */
    public function index(string $index): Builder
    {
        return $this->newQuery()->index($index);
    }

    /**
     * @param array       $parameters
     * @param string|null $index
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
    ): array {
        if (
            ! isset($parameters['index']) &&
            $index = $index ?? $this->index
        ) {
            $parameters['index'] = $index;
        }

        return $this->client->index($parameters);
    }

    /**
     * Route the request to the query class
     *
     * @return Builder<T>
     */
    public function newQuery(): Builder
    {
        return tap(
            new Builder($this),
            fn(Builder $builder) => $builder->index($this->index)
        );
    }

    /**
     * Proxy  calls to the default connection
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     * @throws BadMethodCallException
     */
    public function __call(string $name, array $arguments)
    {
        return $this->forwardCallTo(
            $this->newQuery(),
            $name,
            $arguments
        );
    }
}
