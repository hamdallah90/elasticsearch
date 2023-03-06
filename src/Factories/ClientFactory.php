<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Factories;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Matchory\Elasticsearch\Interfaces\ClientFactoryInterface;
use Psr\Log\LoggerInterface;

class ClientFactory implements ClientFactoryInterface
{
    /**
     * @inheritDoc
     */
    public function createClient(
        array $hosts,
        LoggerInterface|null $logger = null,
        callable|null $handler = null
    ): Client {
        $builder = new ClientBuilder();
        $_hosts = [];

        foreach ($hosts as $host) {
            $_hosts[] = $host['host'] . ":" .  $host['port'];

            if (!empty($host['user']) && !empty($host['pass'])) {
                $builder->setBasicAuthentication($host['user'], $host['pass']);
            }
        }
        
        $builder->setHosts($_hosts);
        
        if ($logger) {
            $builder->setLogger($logger);
        }

        if ($handler) {
            $builder->setHandler($handler);
        }

        return $builder->build();
    }
}
