<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Interfaces;

use Elastic\Elasticsearch\Client;
use Psr\Log\LoggerInterface;

interface ClientFactoryInterface
{
    /**
     * Creates a new client
     *
     * @param array                $hosts
     * @param LoggerInterface|null $logger
     * @param callable|null        $handler
     *
     * @return Client
     */
    public function createClient(
        array $hosts,
        LoggerInterface|null $logger = null,
        callable|null $handler = null
    ): Client;
}
