<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests;

use ArrayObject;
use Elasticsearch\Client;
use Elasticsearch\Namespaces\IndicesNamespace;
use Matchory\Elasticsearch\Builder;
use Matchory\Elasticsearch\Bulk;
use Matchory\Elasticsearch\Connection;
use Matchory\Elasticsearch\ConnectionResolver;
use Matchory\Elasticsearch\Exceptions\DocumentNotFoundException;
use Matchory\Elasticsearch\Index;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use Matchory\Elasticsearch\Model;
use PHPUnit\Framework\Constraint\StringEndsWith;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\ClassAlreadyExistsException;
use PHPUnit\Framework\MockObject\ClassIsFinalException;
use PHPUnit\Framework\MockObject\DuplicateMethodException;
use PHPUnit\Framework\MockObject\InvalidMethodNameException;
use PHPUnit\Framework\MockObject\OriginalConstructorInvocationRequiredException;
use PHPUnit\Framework\MockObject\ReflectionException;
use PHPUnit\Framework\MockObject\Stub\ReturnCallback;
use PHPUnit\Framework\MockObject\UnknownTypeException;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;

use function json_encode;

/**
 * BuilderTest
 *
 * @bundle         Matchory\Elasticsearch\Tests
 * @psalm-suppress PropertyNotSetInConstructor
 */
class BuilderTest extends TestCase
{
    private Client $client;

    private Connection $connection;

    public function testAggregate(): void
    {
        $query = $this
            ->createBuilder()
            ->aggregate('foo')
            ->toArray();

        self::assertSame([
            'aggs' => [
                'foo' => [
                    'terms' => [
                        'field' => 'foo',
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testAggregateWithAlias(): void
    {
        $query = $this
            ->createBuilder()
            ->aggregate('foo', 'bar')
            ->toArray();

        self::assertSame([
            'aggs' => [
                'foo' => [
                    'terms' => [
                        'field' => 'bar',
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testAggregateWithCustomAggregation(): void
    {
        $query = $this
            ->createBuilder()
            ->aggregate('foo', [
                'match' => [
                    'foo' => 'bar',
                ],
            ])
            ->toArray();

        self::assertSame([
            'aggs' => [
                'foo' => [
                    'match' => [
                        'foo' => 'bar',
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testAll(): void
    {
        $query = $this
            ->createBuilder()
            ->all()
            ->toArray();

        self::assertEquals([
            'query' => [
                'match_all' => new ArrayObject(),
            ],
        ], $query['body']);
    }

    public function testAllWithBoost(): void
    {
        $query = $this
            ->createBuilder()
            ->all(42)
            ->toArray();

        self::assertSame([
            'query' => [
                'match_all' => [
                    'boost' => 42.0,
                ],
            ],
        ], $query['body']);
    }

    public function testBody(): void
    {
        $query = $this
            ->createBuilder()
            ->body([
                'foo' => 'bar',
            ])
            ->toArray();

        self::assertSame([
            'foo' => 'bar',
        ], $query['body']);
    }

    public function testBodyGetsMergedWithQueryFragments(): void
    {
        $query = $this
            ->createBuilder()
            ->filter('match', [
                'foo' => 'bar',
            ])
            ->body([
                'foo' => 'bar',
            ])
            ->should('prefix', [
                'baz' => 'quz',
            ])
            ->toArray();

        self::assertSame([
            'foo' => 'bar',
            'query' => [
                'bool' => [
                    'should' => [
                        [
                            'prefix' => [
                                'baz' => 'quz',
                            ],
                        ],
                    ],
                    'filter' => [
                        [
                            'match' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testBulk(): void
    {
        $this->client
            ->expects($this->once())
            ->method('bulk')
            ->with([
                'body' => [
                    [
                        'index' => [
                            '_id' => 'foo',
                            '_index' => 'quz',
                        ],
                    ],
                    [
                        'a' => 1,
                        'b' => 2,
                        'c' => 3,
                    ],
                    [
                        'index' => [
                            '_id' => 'bar',
                            '_index' => 'quz',
                        ],
                    ],
                    [
                        'c' => 42,
                    ],
                ],
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->index('quz')
             ->bulk([
                 'foo' => [
                     'a' => 1,
                     'b' => 2,
                     'c' => 3,
                 ],
                 'bar' => [
                     'c' => 42,
                 ],
             ]);
    }

    public function testBulkCallback(): void
    {
        $this->client
            ->expects($this->once())
            ->method('bulk')
            ->with([
                'body' => [
                    [
                        'index' => [
                            '_id' => 'foo',
                            '_index' => 'quz',
                        ],
                    ],
                    [
                        'a' => 1,
                        'b' => 2,
                        'c' => 3,
                    ],
                    [
                        'delete' => [
                            '_id' => 'bar',
                            '_index' => 'quz',
                        ],
                    ],
                    [
                        'index' => [
                            '_id' => null,
                            '_index' => 'quz',
                        ],
                    ],
                    [
                        'c' => 42,
                    ],
                ],
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->index('quz')
             ->bulk(function (Bulk $bulk) {
                 $bulk
                     ->id('foo')
                     ->insert([
                         'a' => 1,
                         'b' => 2,
                         'c' => 3,
                     ]);

                 $bulk->id('bar')->delete();

                 $bulk
                     ->insert([
                         'c' => 42,
                     ]);
             });
    }

    public function testCachePrefix(): void
    {
        $cacheKey = $this
            ->createBuilder()
            ->id('bar')
            ->cachePrefix('foo')
            ->getCacheKey();

        self::assertStringStartsWith('foo', $cacheKey);
    }

    public function testClear(): void
    {
        $this->client
            ->expects($this->once())
            ->method('clearScroll')
            ->with([
                'body' => [
                    'foo',
                ],
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->scrollId('foo')
             ->clear();
    }

    public function testClearWithExplicitScrollId(): void
    {
        $this->client
            ->expects($this->once())
            ->method('clearScroll')
            ->with([
                'body' => [
                    'bar',
                ],
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->scrollId('foo')
             ->clear('bar');
    }

    public function testClearWithIgnores(): void
    {
        $this->client
            ->expects($this->once())
            ->method('clearScroll')
            ->with([
                'body' => ['foo'],
                'client' => [
                    'ignore' => ['foo', 'bar'],
                ],
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->scrollId('foo')
             ->ignore('foo', 'bar')
             ->clear();
    }

    public function testCount(): void
    {
        $this->client
            ->expects($this->once())
            ->method('count')
            ->with([
                'body' => [],
            ])
            ->willReturn([
                'count' => 0,
            ]);

        $this->createBuilder()
             ->count();
    }

    public function testCreateIndex(): void
    {
        $mockBuilder = $this->getMockBuilder(IndicesNamespace::class);
        $mockBuilder->disableOriginalConstructor();
        $indices = $mockBuilder->getMock();

        $this->client
            ->expects($this->once())
            ->method('indices')
            ->willReturn($indices);

        $indices
            ->expects($this->once())
            ->method('create')
            ->with([
                'index' => 'foo',
                'body' => [],
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->index('foo')
             ->createIndex();
    }

    public function testCreateIndexExplicitName(): void
    {
        $mockBuilder = $this->getMockBuilder(IndicesNamespace::class);
        $mockBuilder->disableOriginalConstructor();
        $indices = $mockBuilder->getMock();

        $this->client
            ->expects($this->once())
            ->method('indices')
            ->willReturn($indices);

        $indices
            ->expects($this->once())
            ->method('create')
            ->with([
                'index' => 'bar',
                'body' => [],
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->index('foo')
             ->createIndex('bar');
    }

    public function testCreateIndexWithReplicas(): void
    {
        $mockBuilder = $this->getMockBuilder(IndicesNamespace::class);
        $mockBuilder->disableOriginalConstructor();
        $indices = $mockBuilder->getMock();

        $this->client
            ->expects($this->once())
            ->method('indices')
            ->willReturn($indices);

        $indices
            ->expects($this->once())
            ->method('create')
            ->with([
                'index' => 'foo',
                'body' => [
                    'settings' => [
                        'number_of_replicas' => 42,
                    ],
                ],
            ])
            ->willReturn([]);

        $this
            ->createBuilder()
            ->index('foo')
            ->createIndex(callback: fn(Index $index) => $index
                ->replicas(42)
            );
    }

    public function testCreateIndexWithSettings(): void
    {
        $mockBuilder = $this->getMockBuilder(IndicesNamespace::class);
        $mockBuilder->disableOriginalConstructor();
        $indices = $mockBuilder->getMock();

        $this->client
            ->expects($this->once())
            ->method('indices')
            ->willReturn($indices);

        $indices
            ->expects($this->once())
            ->method('create')
            ->with([
                'index' => 'foo',
                'body' => [
                    'settings' => [
                        'number_of_shards' => 21,
                        'number_of_replicas' => 21,
                    ],
                ],
            ])
            ->willReturn([]);

        $this
            ->createBuilder()
            ->index('foo')
            ->createIndex(callback: fn(Index $index) => $index
                ->replicas(21)
                ->shards(21)
            );
    }

    public function testCreateIndexWithShards(): void
    {
        $mockBuilder = $this->getMockBuilder(IndicesNamespace::class);
        $mockBuilder->disableOriginalConstructor();
        $indices = $mockBuilder->getMock();

        $this->client
            ->expects($this->once())
            ->method('indices')
            ->willReturn($indices);

        $indices
            ->expects($this->once())
            ->method('create')
            ->with([
                'index' => 'foo',
                'body' => [
                    'settings' => [
                        'number_of_shards' => 42,
                    ],
                ],
            ])
            ->willReturn([]);

        $this
            ->createBuilder()
            ->index('foo')
            ->createIndex(callback: fn(Index $index) => $index
                ->shards(42)
            );
    }

    public function testDecrement(): void
    {
        $this->client
            ->expects($this->once())
            ->method('update')
            ->with([
                'id' => 'foo',
                'body' => [
                    'script' => [
                        'inline' => 'ctx._source.bar -= params.count',
                        'params' => [
                            'count' => 1,
                        ],
                    ],
                ],
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->id('foo')
             ->decrement('bar');
    }

    public function testDecrementWithIgnores(): void
    {
        $this->client
            ->expects($this->once())
            ->method('update')
            ->with([
                'id' => 'foo',
                'body' => [
                    'script' => [
                        'inline' => 'ctx._source.bar -= params.count',
                        'params' => [
                            'count' => 1,
                        ],
                    ],
                ],
                'client' => [
                    'ignore' => ['foo', 'bar'],
                ],
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->id('foo')
             ->ignore('foo', 'bar')
             ->decrement('bar');
    }

    public function testDelete(): void
    {
        $this->client
            ->expects($this->once())
            ->method('delete')
            ->with([
                'id' => 'foo',
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->id('foo')
             ->delete();
    }

    public function testDeleteWithExplicitId(): void
    {
        $this->client
            ->expects($this->once())
            ->method('delete')
            ->with([
                'id' => 'bar',
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->id('foo')
             ->delete('bar');
    }

    public function testDeleteWithIgnores(): void
    {
        $this->client
            ->expects($this->once())
            ->method('delete')
            ->with([
                'id' => 'foo',
                'client' => [
                    'ignore' => ['foo', 'bar'],
                ],
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->id('foo')
             ->ignore('foo', 'bar')
             ->delete();
    }

    public function testDeleteWithIgnoresDoesNotOverrideClientOptions(): void
    {
        $this->client
            ->expects($this->once())
            ->method('delete')
            ->with([
                'id' => 'foo',
                'client' => [
                    'quz' => 'yak',
                    'ignore' => ['foo', 'bar'],
                ],
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->id('foo')
             ->ignore('foo', 'bar')
             ->delete(parameters: [
                 'client' => [
                     'quz' => 'yak',
                 ],
             ]);
    }

    public function testDeleteWithParameters(): void
    {
        $this->client
            ->expects($this->once())
            ->method('delete')
            ->with([
                'id' => 'foo',
                'foo' => 'bar',
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->id('foo')
             ->delete(parameters: [
                 'foo' => 'bar',
             ]);
    }

    public function testDistance(): void
    {
        $query = $this
            ->createBuilder()
            ->distanceFilter('foo', [0, 0], '20km')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'geo_distance' => [
                                'foo' => [0, 0],
                                'distance' => '20km',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testDropIndex(): void
    {
        $mockBuilder = $this->getMockBuilder(IndicesNamespace::class);
        $mockBuilder->disableOriginalConstructor();
        $indices = $mockBuilder->getMock();

        $this->client
            ->expects($this->once())
            ->method('indices')
            ->willReturn($indices);

        $indices
            ->expects($this->once())
            ->method('delete')
            ->with([
                'index' => 'foo',
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->index('foo')
             ->dropIndex();
    }

    public function testDropIndexWithExplicitIndex(): void
    {
        $mockBuilder = $this->getMockBuilder(IndicesNamespace::class);
        $mockBuilder->disableOriginalConstructor();
        $indices = $mockBuilder->getMock();

        $this->client
            ->expects($this->once())
            ->method('indices')
            ->willReturn($indices);

        $indices
            ->expects($this->once())
            ->method('delete')
            ->with([
                'index' => 'bar',
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->index('foo')
             ->dropIndex('bar');
    }

    public function testExclude(): void
    {
        $query = $this
            ->createBuilder()
            ->exclude(['foo', 'bar', 'baz'])
            ->toArray();

        self::assertSame([
            '_source' => [
                'excludes' => ['foo', 'bar', 'baz'],
                'includes' => [],
            ],
        ], $query['body']);
    }

    public function testExcludeDeduplicate(): void
    {
        $query = $this
            ->createBuilder()
            ->exclude(['foo', 'bar', 'baz'])
            ->exclude(['foo'])
            ->exclude(['bar', 'baz'])
            ->exclude(['bar', 'quz'])
            ->toArray();

        self::assertSame([
            '_source' => [
                'excludes' => ['foo', 'bar', 'baz', 'quz'],
                'includes' => [],
            ],
        ], $query['body']);
    }

    public function testExcludeDeduplicateConsideringIncludes(): void
    {
        $query = $this
            ->createBuilder()
            ->include(['foo', 'bar', 'baz'])
            ->exclude(['foo', 'bar'])
            ->exclude(['quz'])
            ->toArray();

        self::assertSame([
            '_source' => [
                'includes' => ['baz'],
                'excludes' => ['foo', 'bar', 'quz'],
            ],
        ], $query['body']);
    }

    public function testExcludeMerged(): void
    {
        $query = $this
            ->createBuilder()
            ->exclude(['foo', 'bar', 'baz'])
            ->exclude(['quz', 'qux', 'zap'])
            ->toArray();

        self::assertSame([
            '_source' => [
                'excludes' => ['foo', 'bar', 'baz', 'quz', 'qux', 'zap'],
                'includes' => [],
            ],
        ], $query['body']);
    }

    public function testExcludeSpreadArgs(): void
    {
        $query = $this
            ->createBuilder()
            ->exclude('foo', 'bar', 'baz')
            ->toArray();

        self::assertSame([
            '_source' => [
                'excludes' => ['foo', 'bar', 'baz'],
                'includes' => [],
            ],
        ], $query['body']);
    }

    public function testExplain(): void
    {
        $this->client
            ->expects($this->once())
            ->method('explain')
            ->with([
                'index' => 'foo',
                'lenient' => false,
                'id' => '42',
                'body' => [
                    'query' => [
                        'bool' => [
                            'filter' => [
                                [
                                    'term' => [
                                        '_id' => '42',
                                    ],
                                ],
                                [
                                    'range' => [
                                        'foo' => [
                                            'gt' => 42,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                '_source' => [
                    'includes' => ['foo', 'bar'],
                    'excludes' => [],
                ],
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->index('foo')
             ->id('42')
             ->include('foo', 'bar')
             ->where('foo', '>', 42)
             ->explain();
    }

    public function testFilter(): void
    {
        $query = $this
            ->createBuilder()
            ->filter('term', [
                'foo' => 'bar',
            ])
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'term' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testFirst(): void
    {
        $this->client
            ->expects($this->once())
            ->method('search')
            ->willReturn([
                'hits' => [
                    'hits' => [
                        [
                            '_source' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ]);

        $result = $this
            ->createBuilder()
            ->all()
            ->first();

        self::assertNotNull($result);
        self::assertSame('bar', $result->foo);
    }

    public function testFirstOr(): void
    {
        $this->client
            ->expects($this->once())
            ->method('search')
            ->willReturn([
                'hits' => [
                    'hits' => [],
                ],
            ]);

        $called = false;
        $this->createBuilder()
             ->all()
             ->firstOr(callback: function () use (&$called) {
                 $called = true;
             });

        self::assertTrue($called);
    }

    public function testFirstOrFail(): void
    {
        $this->client
            ->expects($this->once())
            ->method('search')
            ->willReturn([
                'hits' => [
                    'hits' => [],
                ],
            ]);

        $this->expectException(DocumentNotFoundException::class);
        $this->createBuilder()
             ->all()
             ->firstOrFail();
    }

    public function testFirstWhere(): void
    {
        $this->client
            ->expects($this->once())
            ->method('search')
            ->with([
                'body' => [
                    'query' => [
                        'bool' => [
                            'filter' => [
                                [
                                    'term' => [
                                        'foo' => 'bar',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'from' => 0,
                'size' => 1,
            ])
            ->willReturn([
                'hits' => [
                    'hits' => [
                        [
                            '_source' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ]);

        $result = $this
            ->createBuilder()
            ->firstWhere('foo', 'bar');

        self::assertNotNull($result);
        self::assertSame('bar', $result->foo);
    }

    public function testGet(): void
    {
        $this->client
            ->expects($this->once())
            ->method('search')
            ->willReturn([
                'max_score' => 0,
                'took' => 0,
                'timed_out' => false,
                '_shards' => [],
                'hits' => [
                    'total' => [
                        'value' => 2,
                    ],
                    'hits' => [
                        [
                            '_source' => [
                                'foo' => 'bar',
                            ],
                        ],
                        [
                            '_source' => [
                                'foo' => 'quz',
                            ],
                        ],
                    ],
                ],
            ]);

        $results = $this
            ->createBuilder()
            ->all()
            ->get();

        self::assertNotNull($results);
        self::assertCount(2, $results);
    }

    public function testGetConnection(): void
    {
        $builder = $this->createBuilder();
        $connection = $builder->getConnection();

        self::assertSame($this->connection, $connection);
    }

    public function testGetIndex(): void
    {
        $query = $this->createBuilder();
        self::assertNull($query->getIndex());
        $query->index('foo');
        self::assertSame('foo', $query->getIndex());
    }

    public function testGetIterator(): void
    {
        $this->client
            ->expects($this->once())
            ->method('search')
            ->willReturn([
                'max_score' => 0,
                'took' => 0,
                'timed_out' => false,
                '_shards' => [],
                'hits' => [
                    'total' => [
                        'value' => 2,
                    ],
                    'hits' => [
                        [
                            '_source' => [
                                'foo' => 'bar',
                            ],
                        ],
                        [
                            '_source' => [
                                'foo' => 'quz',
                            ],
                        ],
                    ],
                ],
            ]);

        $results = $this
            ->createBuilder()
            ->all()
            ->getIterator();

        self::assertNotNull($results);
        self::assertCount(2, $results);
    }

    public function testGroupBy(): void
    {
        $query = $this
            ->createBuilder()
            ->groupBy('foo')
            ->toArray();

        self::assertSame([
            'collapse' => [
                'field' => 'foo',
            ],
        ], $query['body']);
    }

    public function testHasNamedScope(): void
    {
        $model = new class extends Model {
            /** @noinspection PhpUnused */
            public function scopeBar(Builder $builder): void
            {
            }
        };
        $builder = $model->newQuery();

        self::assertFalse($builder->hasNamedScope('foo'));
        self::assertTrue($builder->hasNamedScope('bar'));
    }

    public function testHighlight(): void
    {
        $query = $this
            ->createBuilder()
            ->highlight(['foo', 'bar', 'baz'])
            ->toArray();

        self::assertEquals([
            'highlight' => [
                'fields' => [
                    'foo' => new ArrayObject(),
                    'bar' => new ArrayObject(),
                    'baz' => new ArrayObject(),
                ],
            ],
        ], $query['body']);
    }

    public function testId(): void
    {
        $query = $this
            ->createBuilder()
            ->id('foo')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'term' => [
                                '_id' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testIdOmitsFilterForNullId(): void
    {
        $builder = $this->createBuilder();
        $query = $builder->id()->toArray();
        self::assertNull($builder->getId());

        self::assertSame([], $query['body']);
    }

    public function testIdSetsDocumentId(): void
    {
        $query = $this->createBuilder();
        self::assertNull($query->getId());
        $query = $query->id('foo');
        self::assertSame('foo', $query->getId());
    }

    public function testIgnore(): void
    {
        $query = $this
            ->createBuilder()
            ->ignore('foo', 'bar', 'baz');

        self::assertSame(['foo', 'bar', 'baz'], $query->getIgnores());

        $this->client
            ->expects($this->once())
            ->method('search')
            ->with([
                'body' => [],
                'size' => 10,
                'from' => 0,
                'client' => [
                    'ignore' => ['foo', 'bar', 'baz'],
                ],
            ]);

        $query->get();
    }

    public function testInclude(): void
    {
        $query = $this
            ->createBuilder()
            ->include(['foo', 'bar', 'baz'])
            ->toArray();

        self::assertSame([
            '_source' => [
                'includes' => ['foo', 'bar', 'baz'],
                'excludes' => [],
            ],
        ], $query['body']);
    }

    public function testIncludeDeduplicate(): void
    {
        $query = $this
            ->createBuilder()
            ->include(['foo', 'bar', 'baz'])
            ->include(['foo'])
            ->include(['bar', 'baz'])
            ->include(['bar', 'quz'])
            ->toArray();

        self::assertSame([
            '_source' => [
                'includes' => ['foo', 'bar', 'baz', 'quz'],
                'excludes' => [],
            ],
        ], $query['body']);
    }

    public function testIncludeDeduplicateConsideringExcludes(): void
    {
        $query = $this
            ->createBuilder()
            ->exclude(['foo', 'bar', 'baz'])
            ->include(['foo', 'bar'])
            ->include(['quz'])
            ->toArray();

        self::assertSame([
            '_source' => [
                'excludes' => ['baz'],
                'includes' => ['foo', 'bar', 'quz'],
            ],
        ], $query['body']);
    }

    public function testIncludeMerged(): void
    {
        $query = $this
            ->createBuilder()
            ->include('foo', 'bar', 'baz')
            ->include('quz', 'qux', 'zap')
            ->toArray();

        self::assertSame([
            '_source' => [
                'includes' => ['foo', 'bar', 'baz', 'quz', 'qux', 'zap'],
                'excludes' => [],
            ],
        ], $query['body']);
    }

    public function testIncludeSpreadArgs(): void
    {
        $query = $this
            ->createBuilder()
            ->include('foo', 'bar', 'baz')
            ->toArray();

        self::assertSame([
            '_source' => [
                'includes' => ['foo', 'bar', 'baz'],
                'excludes' => [],
            ],
        ], $query['body']);
    }

    public function testIncrement(): void
    {
        $this->client
            ->expects($this->once())
            ->method('update')
            ->with([
                'id' => 'foo',
                'body' => [
                    'script' => [
                        'inline' => 'ctx._source.bar += params.count',
                        'params' => [
                            'count' => 1,
                        ],
                    ],
                ],
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->id('foo')
             ->increment('bar');
    }

    public function testIncrementWithIgnores(): void
    {
        $this->client
            ->expects($this->once())
            ->method('update')
            ->with([
                'id' => 'foo',
                'body' => [
                    'script' => [
                        'inline' => 'ctx._source.bar += params.count',
                        'params' => [
                            'count' => 1,
                        ],
                    ],
                ],
                'client' => [
                    'ignore' => ['foo', 'bar'],
                ],
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->id('foo')
             ->ignore('foo', 'bar')
             ->increment('bar');
    }

    public function testIndex(): void
    {
        $query = $this
            ->createBuilder()
            ->index('foo')
            ->all()
            ->toArray();

        self::assertEquals([
            'body' => [
                'query' => [
                    'match_all' => new ArrayObject(),
                ],
            ],
            'index' => 'foo',
            'size' => 10,
            'from' => 0,
        ], $query);
    }

    public function testIndexExists(): void
    {
        $mockBuilder = $this->getMockBuilder(IndicesNamespace::class);
        $mockBuilder->disableOriginalConstructor();
        $indices = $mockBuilder->getMock();

        $this->client
            ->expects($this->exactly(2))
            ->method('indices')
            ->willReturn($indices);

        $indices
            ->expects($this->exactly(2))
            ->method('exists')
            ->withConsecutive(
                [['index' => 'foo']],
                [['index' => 'bar']]
            )
            ->willReturn(true, false);

        $exists1 = $this
            ->createBuilder()
            ->index('foo')
            ->indexExists();

        $exists2 = $this
            ->createBuilder()
            ->index('bar')
            ->indexExists();

        self::assertTrue($exists1);
        self::assertFalse($exists2);
    }

    public function testIndexExistsWithExplicitIndex(): void
    {
        $mockBuilder = $this->getMockBuilder(IndicesNamespace::class);
        $mockBuilder->disableOriginalConstructor();
        $indices = $mockBuilder->getMock();

        $this->client
            ->expects($this->once())
            ->method('indices')
            ->willReturn($indices);

        $indices
            ->expects($this->once())
            ->method('exists')
            ->with([
                'index' => 'bar',
            ])
            ->willReturn(true);

        $exists = $this
            ->createBuilder()
            ->index('foo')
            ->indexExists('bar');

        self::assertTrue($exists);
    }

    public function testInsert(): void
    {
        $this->client
            ->expects($this->once())
            ->method('index')
            ->with([
                'body' => [
                    'foo' => 'bar',
                ],
            ]);

        $this->createBuilder()
             ->insert([
                 'foo' => 'bar',
             ]);
    }

    public function testInsertWithId(): void
    {
        $this->client
            ->expects($this->once())
            ->method('index')
            ->with([
                'body' => [
                    'foo' => 'bar',
                ],
                'id' => 'quz',
            ]);

        $this->createBuilder()
             ->insert([
                 'foo' => 'bar',
             ], 'quz');
    }

    public function testJsonSerialize(): void
    {
        $query = $this
            ->createBuilder()
            ->take(42)
            ->skip(20)
            ->where('foo', 'bar');

        self::assertSame($query->toArray(), $query->jsonSerialize());
    }

    public function testMatchFilter(): void
    {
        $query = $this
            ->createBuilder()
            ->matchFilter('foo', 'bar')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'match' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testMatchFilterWithCustomParameters(): void
    {
        $query = $this
            ->createBuilder()
            ->matchFilter('foo', [
                'query' => 'bar',
                'operator' => 'AND',
            ])
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'match' => [
                                'foo' => [
                                    'query' => 'bar',
                                    'operator' => 'AND',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testMinimumShouldMatch(): void
    {
        $query = $this
            ->createBuilder()
            ->should('match', [
                'foo' => 'bar',
            ])
            ->minimumShouldMatch(42)
            ->toArray();

        self::assertSame(
            [
                'query' => [
                    'bool' => [
                        'minimum_should_match' => 42,
                        'should' => [
                            [
                                'match' => [
                                    'foo' => 'bar',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            $query['body']
        );
    }

    public function testMinimumShouldMatchNotPresentIfNoShouldConditions(): void
    {
        $query = $this
            ->createBuilder()
            ->must('match', [
                'foo' => 'bar',
            ])
            ->minimumShouldMatch(42)
            ->toArray();

        self::assertSame(
            [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'match' => [
                                    'foo' => 'bar',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            $query['body']
        );
    }

    public function testMultipleAggregations(): void
    {
        $query = $this
            ->createBuilder()
            ->aggregate('foo')
            ->aggregate('bar')
            ->aggregate('baz')
            ->toArray();

        self::assertSame([
            'aggs' => [
                'foo' => [
                    'terms' => [
                        'field' => 'foo',
                    ],
                ],
                'bar' => [
                    'terms' => [
                        'field' => 'bar',
                    ],
                ],
                'baz' => [
                    'terms' => [
                        'field' => 'baz',
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testMust(): void
    {
        $query = $this
            ->createBuilder()
            ->must('match', [
                'foo' => 'bar',
            ])
            ->toArray();

        self::assertSame(
            [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'match' => [
                                    'foo' => 'bar',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            $query['body']
        );
    }

    public function testMustNot(): void
    {
        $query = $this
            ->createBuilder()
            ->mustNot('match', [
                'foo' => 'bar',
            ])
            ->toArray();

        self::assertSame(
            [
                'query' => [
                    'bool' => [
                        'must_not' => [
                            [
                                'match' => [
                                    'foo' => 'bar',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            $query['body']
        );
    }

    public function testNested(): void
    {
        $query = $this
            ->createBuilder()
            ->nested('foo', [
                'match' => [
                    'baz' => 'quz',
                ],
            ])
            ->toArray();

        self::assertSame(
            [
                'query' => [
                    'nested' => [
                        'score_mode' => 'avg',
                        'path' => 'foo',
                        'query' => [
                            'match' => [
                                'baz' => 'quz',
                            ],
                        ],
                    ],
                ],
            ],
            $query['body']
        );
    }

    public function testNestedWithBuilderQuery(): void
    {
        $nestedQuery = $this->createBuilder()->must('match', [
            'foo.bar' => 'baz',
        ]);
        $query = $this
            ->createBuilder()
            ->nested('foo', $nestedQuery)
            ->toArray();

        self::assertSame(
            [
                'query' => [
                    'nested' => [
                        'score_mode' => 'avg',
                        'path' => 'foo',
                        'query' => [
                            'bool' => [
                                'must' => [
                                    [
                                        'match' => [
                                            'foo.bar' => 'baz',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            $query['body']
        );
    }

    public function testNestedWithCustomScoreMode(): void
    {
        $query = $this
            ->createBuilder()
            ->nested('foo', [
                'match' => [
                    'baz' => 'quz',
                ],
            ], 'sum')
            ->toArray();

        self::assertSame(
            [
                'query' => [
                    'nested' => [
                        'score_mode' => 'sum',
                        'path' => 'foo',
                        'query' => [
                            'match' => [
                                'baz' => 'quz',
                            ],
                        ],
                    ],
                ],
            ],
            $query['body']
        );
    }

    public function testNone(): void
    {
        $query = $this
            ->createBuilder()
            ->none()
            ->toArray();

        self::assertEquals(
            [
                'query' => [
                    'match_none' => new ArrayObject(),
                ],
            ],
            $query['body']
        );
    }

    public function testOrderBy(): void
    {
        $query = $this
            ->createBuilder()
            ->orderBy('foo')
            ->toArray();

        self::assertSame([
            'sort' => [
                [
                    'foo' => 'asc',
                ],
            ],
        ], $query['body']);
    }

    public function testPaginate(): void
    {
        $this->client
            ->expects($this->once())
            ->method('search')
            ->with([
                'body' => [],
                'from' => 0,
                'size' => 2,
            ])
            ->willReturn([
                'max_score' => 0,
                'took' => 0,
                'timed_out' => false,
                '_shards' => [],
                'hits' => [
                    'total' => [
                        'value' => 42,
                    ],
                    'hits' => [
                        [
                            '_source' => [
                                'foo' => 'bar',
                            ],
                        ],
                        [
                            '_source' => [
                                'foo' => 'baz',
                            ],
                        ],
                    ],
                ],
            ]);

        $pagination = $this
            ->createBuilder()
            ->paginate(perPage: 2);

        self::assertCount(2, $pagination);
        self::assertSame(42, $pagination->total());
        self::assertSame(1, $pagination->currentPage());
        self::assertSame(21, $pagination->lastPage());
    }

    public function testPinned(): void
    {
        $query = $this
            ->createBuilder()
            ->pinned(['a', 'b'])
            ->toArray();

        self::assertEquals([
            'query' => [
                'pinned' => [
                    'ids' => ['a', 'b'],
                    'organic' => [
                        'match_none' => new ArrayObject(),
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testPinnedWithOrganicQuery(): void
    {
        $query = $this
            ->createBuilder()
            ->pinned(['a', 'b'], [
                'match' => [
                    'foo' => 'bar',
                ],
            ])
            ->toArray();

        self::assertEquals([
            'query' => [
                'pinned' => [
                    'ids' => ['a', 'b'],
                    'organic' => [
                        'match' => [
                            'foo' => 'bar',
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testPinnedWithOrganicQueryFromBuilder(): void
    {
        $organic = $this
            ->createBuilder()
            ->wildcardFilter('foo', 'b*r');
        $query = $this
            ->createBuilder()
            ->pinned(['a', 'b'], $organic)
            ->toArray();

        self::assertEquals([
            'query' => [
                'pinned' => [
                    'ids' => ['a', 'b'],
                    'organic' => [
                        'bool' => [
                            'filter' => [
                                [
                                    'wildcard' => [
                                        'foo' => [
                                            'value' => 'b*r',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testPrefixFilter(): void
    {
        $query = $this
            ->createBuilder()
            ->prefixFilter('foo', 'ba')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'prefix' => [
                                'foo' => [
                                    'value' => 'ba',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testPrefixFilterCaseInsensitive(): void
    {
        $query = $this
            ->createBuilder()
            ->prefixFilter('foo', 'ba', false)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'prefix' => [
                                'foo' => [
                                    'value' => 'ba',
                                    'case_insensitive' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testRangeFilter(): void
    {
        $query = $this
            ->createBuilder()
            ->rangeFilter('foo', 'gt', 42)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'range' => [
                                'foo' => [
                                    'gt' => 42,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testRangeFilterWithCallable(): void
    {
        $query = $this
            ->createBuilder()
            ->rangeFilter('foo', fn(Builder $builder, string $field) => [
                'lt' => 42,
            ])
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'range' => [
                                'foo' => [
                                    'lt' => 42,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testRangeFilterWithParameters(): void
    {
        $query = $this
            ->createBuilder()
            ->rangeFilter('foo', [
                'gt' => 42,
            ])
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'range' => [
                                'foo' => [
                                    'gt' => 42,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testRaw(): void
    {
        $builder = $this->createBuilder();
        $client = $builder->raw();

        self::assertSame($this->client, $client);
    }

    public function testRegexpFilterWithCaseInsensitivity(): void
    {
        $query = $this
            ->createBuilder()
            ->regexpFilter('foo', '^bar$', caseSensitive: false)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'regexp' => [
                                'foo' => [
                                    'value' => '^bar$',
                                    'case_insensitive' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testRegexpFilterWithExplicitOptions(): void
    {
        $query = $this
            ->createBuilder()
            ->regexpFilter('foo', [
                'value' => '^bar$',
                'flags' => 'ALL',
                'case_insensitive' => true,
                'max_determinized_states' => 10_000,
                'rewrite' => 'constant_score',
            ])
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'regexp' => [
                                'foo' => [
                                    'value' => '^bar$',
                                    'flags' => 'ALL',
                                    'case_insensitive' => true,
                                    'max_determinized_states' => 10_000,
                                    'rewrite' => 'constant_score',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testRegexpFilterWithFlags(): void
    {
        $query = $this
            ->createBuilder()
            ->regexpFilter(
                'foo',
                '^bar$',
                Builder::REGEXP_FLAG_ANYSTRING | Builder::REGEXP_FLAG_INTERSECTION
            )
            ->toArray();

        /** @noinspection SpellCheckingInspection */
        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'regexp' => [
                                'foo' => [
                                    'value' => '^bar$',
                                    'flags' => 'INTERSECTION|ANYSTRING',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testRegexpFilterWithMaxDeterminizedStates(): void
    {
        $query = $this
            ->createBuilder()
            ->regexpFilter('foo', '^bar$', maxDeterminizedStates: 42)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'regexp' => [
                                'foo' => [
                                    'value' => '^bar$',
                                    'max_determinized_states' => 42,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testRegexpFilterWithSimpleExpression(): void
    {
        $query = $this
            ->createBuilder()
            ->regexpFilter('foo', '^bar$')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'regexp' => [
                                'foo' => '^bar$',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testRemember(): void
    {
        $cacheMock = $this->getMockBuilder(CacheInterface::class);
        $cache = $cacheMock->getMock();
        $connection = new Connection(
            $this->client,
            $cache
        );
        $builder = new Builder($connection);

        $esResponse = [
            'max_score' => 0,
            'took' => 0,
            'timed_out' => false,
            '_shards' => [],
            'hits' => [
                'total' => [
                    'value' => 2,
                ],
                'hits' => [
                    [
                        '_source' => [
                            'foo' => 'bar',
                        ],
                    ],
                    [
                        '_source' => [
                            'foo' => 'quz',
                        ],
                    ],
                ],
            ],
        ];

        $this->client
            ->expects($this->once())
            ->method('search')
            ->willReturn($esResponse);

        $cache->expects($this->exactly(2))
              ->method('get')
              ->with(new StringEndsWith('foo'))
              ->willReturn(
              // Make sure to throw as an actual cache implementation would
              // do if the key cannot be found
                  new ReturnCallback(fn() => throw new class
                      extends RuntimeException
                      implements InvalidArgumentException {
                  }),
                  $esResponse
              );

        $cache->expects($this->once())
              ->method('set')
              ->with(new StringEndsWith('foo'));

        $builder
            ->remember(42, 'foo')
            ->all()
            ->get();

        $builder
            ->remember(42, 'foo')
            ->all()
            ->get();
    }

    public function testRememberForever(): void
    {
        $cacheMock = $this->getMockBuilder(CacheInterface::class);
        $cache = $cacheMock->getMock();
        $connection = new Connection(
            $this->client,
            $cache
        );
        $builder = new Builder($connection);

        $esResponse = [
            'max_score' => 0,
            'took' => 0,
            'timed_out' => false,
            '_shards' => [],
            'hits' => [
                'total' => [
                    'value' => 2,
                ],
                'hits' => [
                    [
                        '_source' => [
                            'foo' => 'bar',
                        ],
                    ],
                    [
                        '_source' => [
                            'foo' => 'quz',
                        ],
                    ],
                ],
            ],
        ];

        $this->client
            ->expects($this->once())
            ->method('search')
            ->willReturn($esResponse);

        $cache->expects($this->exactly(2))
              ->method('get')
              ->with(new StringEndsWith('foo'))
              ->willReturn(
              // Make sure to throw as an actual cache implementation would
              // do if the key cannot be found
                  new ReturnCallback(fn() => throw new class
                      extends RuntimeException
                      implements InvalidArgumentException {
                  }),
                  $esResponse
              );

        $cache->expects($this->once())
              ->method('set')
              ->with(new StringEndsWith('foo'));

        $builder
            ->rememberForever('foo')
            ->all()
            ->get();

        $builder
            ->rememberForever('foo')
            ->all()
            ->get();
    }

    public function testRemovedScopes(): void
    {
        $mock = $this->getMockBuilder(Model::class);
        $mock->addMethods(['scopeFoo', 'scopeBar']);
        $model = $mock->getMock();
        $builder = $model->newQuery();

        $model::addGlobalScope('foo', static fn() => []);
        $model::addGlobalScope('bar', static fn() => []);
        $model::addGlobalScope('baz', static fn() => []);

        $builder->withoutGlobalScopes(['foo', 'bar']);

        self::assertSame([
            'foo',
            'bar',
        ], $builder->removedScopes());
    }

    public function testScopes(): void
    {
        $mock = $this->getMockBuilder(Model::class);
        $mock->addMethods(['scopeFoo', 'scopeBar']);
        $model = $mock->getMock();
        $builder = $model->newQuery();

        $model->expects($this->once())
              ->method('scopeFoo')
              ->with($builder)
              ->willReturn($builder);

        $model->expects($this->once())
              ->method('scopeBar')
              ->with($builder, 'a', 'b', 'c')
              ->willReturn($builder);

        $builder->scopes([
            'foo',
            'bar' => ['a', 'b', 'c'],
        ]);
    }

    public function testScript(): void
    {
        $this->client
            ->expects($this->once())
            ->method('update')
            ->with([
                'id' => 'quz',
                'body' => [
                    'script' => [
                        'inline' => 'ctx.foo = "bar"',
                        'params' => [],
                    ],
                ],
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->id('quz')
             ->script('ctx.foo = "bar"');
    }

    public function testScroll(): void
    {
        $builder = $this
            ->createBuilder()
            ->scrollId('foo')
            ->scroll('42m');

        self::assertSame('42m', $builder->getScroll());

        $this->client
            ->expects($this->once())
            ->method('scroll')
            ->with([
                'scroll' => '42m',
                'scroll_id' => 'foo',
            ]);

        $builder->get();
    }

    public function testSearchType(): void
    {
        $builder = $this
            ->createBuilder()
            ->searchType('foo');

        self::assertSame('foo', $builder->getSearchType());

        $query = $builder->toArray();

        self::assertSame([
            'body' => [],
            'from' => 0,
            'size' => 10,
            'search_type' => 'foo',
        ], $query);
    }

    public function testSet(): void
    {
        $query = $this
            ->createBuilder()
            ->set('query.bool.should.0.match', [
                'foo' => 'bar',
            ])
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'should' => [
                        [
                            'match' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testSetModel(): void
    {
        $model = new class extends Model {
        };

        $builder = $this->createBuilder();
        self::assertNotSame($model, $builder->getModel());
        $builder->setModel($model);
        self::assertSame($model, $builder->getModel());
    }

    public function testShould(): void
    {
        $query = $this
            ->createBuilder()
            ->should('match', [
                'foo' => 'bar',
            ])
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'should' => [
                        [
                            'match' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testSkip(): void
    {
        $query = $this
            ->createBuilder()
            ->take(24)
            ->skip(12)
            ->toArray();

        self::assertSame([
            'body' => [],
            'from' => 12,
            'size' => 24,
        ], $query);
    }

    public function testSource(): void
    {
        $query = $this
            ->createBuilder()
            ->exclude(['foo', 'bar', 'baz'])
            ->include(['quz', 'qux', 'zap'])
            ->toArray();

        self::assertSame([
            '_source' => [
                'excludes' => ['foo', 'bar', 'baz',],
                'includes' => ['quz', 'qux', 'zap'],
            ],
        ], $query['body']);
    }

    public function testSuggest(): void
    {
        $query = $this
            ->createBuilder()
            ->suggest('foo', [
                'term' => [
                    'baz' => 'quz',
                ],
            ])
            ->toArray();

        self::assertSame([
            'suggest' => [
                'foo' => [
                    'term' => [
                        'baz' => 'quz',
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testTake(): void
    {
        $query = $this
            ->createBuilder()
            ->take(12)
            ->toArray();

        self::assertSame([
            'body' => [],
            'from' => 0,
            'size' => 12,
        ], $query);
    }

    public function testTermFilter(): void
    {
        $query = $this
            ->createBuilder()
            ->termFilter('foo', 'bar')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'term' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testTermFilterWithBoost(): void
    {
        $query = $this
            ->createBuilder()
            ->termFilter('foo', 'bar', 2.5)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'term' => [
                                'foo' => 'bar',
                                'boost' => 2.5,
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testTermFilterWithIntegerBoost(): void
    {
        $query = $this
            ->createBuilder()
            ->termFilter('foo', 'bar', 2)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'term' => [
                                'foo' => 'bar',
                                'boost' => 2.0,
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testTermsFilter(): void
    {
        $query = $this
            ->createBuilder()
            ->termsFilter('foo', 'bar')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'terms' => [
                                'foo' => ['bar'],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testTermsFilterWithBoost(): void
    {
        $query = $this
            ->createBuilder()
            ->termsFilter('foo', ['bar', 'baz'], 2.5)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'terms' => [
                                'foo' => ['bar', 'baz'],
                                'boost' => 2.5,
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testTermsFilterWithMultipleTerms(): void
    {
        $query = $this
            ->createBuilder()
            ->termsFilter('foo', ['bar', 'baz'])
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'terms' => [
                                'foo' => ['bar', 'baz'],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testToJson(): void
    {
        $query = $this
            ->createBuilder()
            ->all();

        self::assertJsonStringEqualsJsonString(
            json_encode([
                'body' => [
                    'query' => [
                        'match_all' => new ArrayObject(),
                    ],
                ],
                'from' => 0,
                'size' => 10,
            ], JSON_THROW_ON_ERROR),
            $query->toJson()
        );
    }

    /**
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @deprecated
     */
    public function testUnselect(): void
    {
        $query = $this
            ->createBuilder()
            ->unselect(['foo', 'bar', 'baz'])
            ->toArray();

        self::assertSame([
            '_source' => [
                'excludes' => ['foo', 'bar', 'baz'],
                'includes' => [],
            ],
        ], $query['body']);
    }

    public function testUpdate(): void
    {
        $this->client
            ->expects($this->once())
            ->method('update')
            ->with([
                'id' => 'foo',
                'body' => [
                    'doc' => [
                        'bar' => 'baz',
                    ],
                ],
            ])
            ->willReturn([]);

        $this->createBuilder()
             ->id('foo')
             ->update([
                 'bar' => 'baz',
             ]);
    }

    public function testWhereBetween(): void
    {
        $query = $this
            ->createBuilder()
            ->whereBetween('foo', 10, 20)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'range' => [
                                'foo' => [
                                    'gte' => 10,
                                    'lte' => 20,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereDefaultsToEquality(): void
    {
        $query = $this
            ->createBuilder()
            ->where('foo', '!!invalid', 'bar')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'term' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereExists(): void
    {
        $query = $this
            ->createBuilder()
            ->whereExists('foo')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'exists' => [
                                'field' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereExistsNegated(): void
    {
        $query = $this
            ->createBuilder()
            ->whereExists('foo', false)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'must_not' => [
                        [
                            'exists' => [
                                'field' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereExplicitEquality(): void
    {
        $query = $this
            ->createBuilder()
            ->where('foo', '=', 'bar')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'term' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereExplicitExists(): void
    {
        $query = $this
            ->createBuilder()
            ->where('foo', 'exists', true)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'exists' => [
                                'field' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereExplicitNotExists(): void
    {
        $query = $this
            ->createBuilder()
            ->where('foo', 'exists', false)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'must_not' => [
                        [
                            'exists' => [
                                'field' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereGreaterThan(): void
    {
        $query = $this
            ->createBuilder()
            ->where('foo', '>', 42)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'range' => [
                                'foo' => [
                                    'gt' => 42,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereGreaterThanOrEqual(): void
    {
        $query = $this
            ->createBuilder()
            ->where('foo', '>=', 42)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'range' => [
                                'foo' => [
                                    'gte' => 42,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereImplicitExists(): void
    {
        $query = $this
            ->createBuilder()
            ->where('foo', 'exists')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'exists' => [
                                'field' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereIn(): void
    {
        $query = $this
            ->createBuilder()
            ->whereIn('foo', [1, 3, 5, 7, 9])
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'terms' => [
                                'foo' => [1, 3, 5, 7, 9],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereLike(): void
    {
        $query = $this
            ->createBuilder()
            ->where('foo', 'like', 'bar')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'match' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereLowerThan(): void
    {
        $query = $this
            ->createBuilder()
            ->where('foo', '<', 42)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'range' => [
                                'foo' => [
                                    'lt' => 42,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereLowerThanOrEqual(): void
    {
        $query = $this
            ->createBuilder()
            ->where('foo', '<=', 42)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'range' => [
                                'foo' => [
                                    'lte' => 42,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereNotBetween(): void
    {
        $query = $this
            ->createBuilder()
            ->whereNotBetween('foo', 10, 20)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'must_not' => [
                        [
                            'range' => [
                                'foo' => [
                                    'gte' => 10,
                                    'lte' => 20,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereNotDefaultsToEquality(): void
    {
        $query = $this
            ->createBuilder()
            ->whereNot('foo', '!!invalid', 'bar')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'must_not' => [
                        [
                            'term' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereNotExplicitEquality(): void
    {
        $query = $this
            ->createBuilder()
            ->whereNot('foo', '=', 'bar')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'must_not' => [
                        [
                            'term' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereNotExplicitExists(): void
    {
        $query = $this
            ->createBuilder()
            ->whereNot('foo', 'exists', true)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'must_not' => [
                        [
                            'exists' => [
                                'field' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereNotExplicitNotExists(): void
    {
        $query = $this
            ->createBuilder()
            ->whereNot('foo', 'exists', false)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'exists' => [
                                'field' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereNotGreaterThan(): void
    {
        $query = $this
            ->createBuilder()
            ->whereNot('foo', '>', 42)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'must_not' => [
                        [
                            'range' => [
                                'foo' => [
                                    'gt' => 42,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereNotGreaterThanOrEqual(): void
    {
        $query = $this
            ->createBuilder()
            ->whereNot('foo', '>=', 42)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'must_not' => [
                        [
                            'range' => [
                                'foo' => [
                                    'gte' => 42,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereNotIn(): void
    {
        $query = $this
            ->createBuilder()
            ->whereNotIn('foo', [1, 3, 5, 7, 9])
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'must_not' => [
                        [
                            'terms' => [
                                'foo' => [1, 3, 5, 7, 9],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereNotLike(): void
    {
        $query = $this
            ->createBuilder()
            ->whereNot('foo', 'like', 'bar')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'must_not' => [
                        [
                            'match' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereNotLowerThan(): void
    {
        $query = $this
            ->createBuilder()
            ->whereNot('foo', '<', 42)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'must_not' => [
                        [
                            'range' => [
                                'foo' => [
                                    'lt' => 42,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereNotLowerThanOrEqual(): void
    {
        $query = $this
            ->createBuilder()
            ->whereNot('foo', '<=', 42)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'must_not' => [
                        [
                            'range' => [
                                'foo' => [
                                    'lte' => 42,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereNotSimpleEquality(): void
    {
        $query = $this
            ->createBuilder()
            ->whereNot('foo', 'bar')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'must_not' => [
                        [
                            'term' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWhereSimpleEquality(): void
    {
        $query = $this
            ->createBuilder()
            ->where('foo', 'bar')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'term' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWildcardFilter(): void
    {
        $query = $this
            ->createBuilder()
            ->wildcardFilter('foo', 'b*r')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'wildcard' => [
                                'foo' => [
                                    'value' => 'b*r',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWildcardFilterWithBoost(): void
    {
        $query = $this
            ->createBuilder()
            ->wildcardFilter('foo', 'b*r', 2.5)
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'wildcard' => [
                                'foo' => [
                                    'value' => 'b*r',
                                    'boost' => 2.5,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWithGlobalScope(): void
    {
        $model = new Model();
        $query = $model
            ->withGlobalScope(
                'foo',
                static fn(Builder $query) => $query->where(
                    'foo',
                    'bar'
                )
            )
            ->where('baz', 'quz')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'term' => [
                                'baz' => 'quz',
                            ],
                        ],
                        [
                            'term' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);
    }

    public function testWithoutGlobalScope(): void
    {
        Model::setConnectionResolver(new ConnectionResolver([
            '' => $this->connection,
        ]));
        Model::addGlobalScope(
            'foo',
            static fn(Builder $query) => $query->where(
                'foo',
                'bar'
            ));

        $model = new Model();
        $query = $model
            ->withoutGlobalScope('foo')
            ->where('baz', 'quz')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'term' => [
                                'baz' => 'quz',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);

        $model::addGlobalScope('foo', static fn() => []);

        self::assertTrue($model::hasGlobalScope('foo'));
        $query = $model->newQuery();
        self::assertNotContains('foo', $query->removedScopes());
        $query = $query->withoutGlobalScope('foo');
        self::assertContains('foo', $query->removedScopes());
    }

    public function testWithoutGlobalScopes(): void
    {
        Model::setConnectionResolver(new ConnectionResolver([
            '' => $this->connection,
        ]));
        Model::addGlobalScope(
            'foo',
            static fn(Builder $query) => $query->where(
                'foo',
                'bar'
            ));

        $model = new Model();
        $query = $model
            ->withoutGlobalScopes()
            ->where('baz', 'quz')
            ->toArray();

        self::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'term' => [
                                'baz' => 'quz',
                            ],
                        ],
                    ],
                ],
            ],
        ], $query['body']);

        $model::addGlobalScope('foo', static fn() => []);
        $model::addGlobalScope('bar', static fn() => []);

        self::assertTrue($model::hasGlobalScope('foo'));
        $query = $model->newQuery();
        self::assertNotContains('foo', $query->removedScopes());
        self::assertNotContains('bar', $query->removedScopes());
        $query = $query->withoutGlobalScopes();
        self::assertContains('foo', $query->removedScopes());
        self::assertContains('bar', $query->removedScopes());
    }

    /**
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws ClassAlreadyExistsException
     * @throws ClassIsFinalException
     * @throws DuplicateMethodException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws \PHPUnit\Framework\MockObject\RuntimeException
     * @throws UnknownTypeException
     */
    protected function setUp(): void
    {
        $mockBuilder = $this->getMockBuilder(Client::class);
        $mockBuilder->disableOriginalConstructor();
        $this->client = $mockBuilder->getMock();
        $this->connection = new Connection($this->client);

        /** @var ConnectionResolverInterface<Model> $resolver */
        $resolver = new ConnectionResolver([
            '' => $this->connection,
        ]);

        Model::setConnectionResolver($resolver);
    }

    private function createBuilder(): Builder
    {
        return new Builder($this->connection);
    }
}
