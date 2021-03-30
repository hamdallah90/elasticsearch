<?php
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests;

use Elasticsearch\Client;
use Illuminate\Foundation\Application;
use Matchory\Elasticsearch\Connection;
use Matchory\Elasticsearch\ConnectionResolver;
use Matchory\Elasticsearch\ElasticsearchServiceProvider;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\InvalidArgumentException;
use PHPUnit\Framework\MockObject\ClassAlreadyExistsException;
use PHPUnit\Framework\MockObject\ClassIsFinalException;
use PHPUnit\Framework\MockObject\DuplicateMethodException;
use PHPUnit\Framework\MockObject\InvalidMethodNameException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\OriginalConstructorInvocationRequiredException;
use PHPUnit\Framework\MockObject\ReflectionException;
use PHPUnit\Framework\MockObject\RuntimeException;
use PHPUnit\Framework\MockObject\UnknownTypeException;

class ConnectionTest extends TestCase
{
    protected MockObject|null $elasticsearchClient = null;

    /**
     * @return ConnectionResolver
     * @throws InvalidArgumentException
     * @throws ClassAlreadyExistsException
     * @throws ClassIsFinalException
     * @throws DuplicateMethodException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     */
    public function createConnectionResolver(): ConnectionResolver
    {
        /** @var Client $mock */
        $mock = $this->mockClient();

        $connection = new Connection($mock);
        $connectionName = $this->getDefaultConnectionName();

        $resolver = new ConnectionResolver([
            $connectionName => $connection,
        ]);

        $resolver->setDefaultConnection($connectionName);

        return $resolver;
    }

    /**
     * @return MockObject<Client>
     * @throws ClassAlreadyExistsException
     * @throws ClassIsFinalException
     * @throws DuplicateMethodException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     */
    public function mockClient(): MockObject
    {
        if ( ! $this->elasticsearchClient) {
            $this->elasticsearchClient = $this
                ->getMockBuilder(Client::class)
                ->disableOriginalConstructor()
                ->getMock();
        }

        return $this->elasticsearchClient;
    }

    public function testIndexReturnsNewQueryOnIndex(): void
    {
        /** @var ConnectionInterface $connection */
        $connection = $this->app->make(ConnectionInterface::class);
        $query = $connection->index('foo');

        self::assertSame('foo', $query->getIndex());
    }

    protected function getDefaultConnectionName(): string
    {
        return 'default';
    }

    protected function getEnvironmentSetUp($app): void
    {
        $this->registerResolver($app);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ElasticsearchServiceProvider::class,
        ];
    }

    /**
     * @param Application $application
     *
     * @throws ClassAlreadyExistsException
     * @throws ClassIsFinalException
     * @throws DuplicateMethodException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     */
    protected function registerResolver(Application $application): void
    {
        $resolver = $this->createConnectionResolver();

        $application->instance(
            ConnectionResolverInterface::class,
            $resolver
        );
    }
}
