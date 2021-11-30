<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Matchory\Elasticsearch\Tests\Traits;

use Elasticsearch\Client;
use Matchory\Elasticsearch\Connection;
use Matchory\Elasticsearch\Query;

/**
 * Class ESQueryTrait
 */
trait ESQueryTrait
{
    /**
     * Test index name
     *
     * @var string
     */
    protected $index = 'my_index';

    /**
     * Test query offset
     *
     * @var int
     */
    protected $skip = 0;

    /**
     * Test query limit
     *
     * @var int
     */
    protected $take = 10;

    protected function getClient(): Client
    {
        return $this
            ->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function getConnection(): Connection
    {
        return new Connection($this->getClient());
    }

    /**
     * Expected query array
     *
     * @param array $body
     *
     * @return array
     */
    protected function getQueryArray(array $body = []): array
    {
        return [
            'index' => $this->index,
            'body' => $body,
            'from' => $this->skip,
            'size' => $this->take,
        ];
    }

    /**
     * ES query object
     *
     * @param Query|null $query
     *
     * @return Query
     */
    protected function getQueryObject(?Query $query = null): Query
    {
        return ($query ?? new Query($this->getConnection()))
            ->index($this->index)
            ->take($this->take)
            ->skip($this->skip);
    }
}
