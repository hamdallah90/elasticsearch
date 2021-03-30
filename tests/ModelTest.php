<?php
/** @noinspection PhpUndefinedFieldInspection, PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests;

use ArrayAccess;
use ArrayObject;
use Elasticsearch\Client;
use Illuminate\Contracts\Queue\QueueableEntity;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use JsonSerializable;
use Matchory\Elasticsearch\Connection;
use Matchory\Elasticsearch\ConnectionResolver;
use Matchory\Elasticsearch\ElasticsearchServiceProvider;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use Matchory\Elasticsearch\Model;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\MockObject\ClassAlreadyExistsException;
use PHPUnit\Framework\MockObject\ClassIsFinalException;
use PHPUnit\Framework\MockObject\DuplicateMethodException;
use PHPUnit\Framework\MockObject\InvalidMethodNameException;
use PHPUnit\Framework\MockObject\OriginalConstructorInvocationRequiredException;
use PHPUnit\Framework\MockObject\ReflectionException;
use PHPUnit\Framework\MockObject\UnknownTypeException;

class ModelTest extends TestCase
{
    private Client $client;

    private Connection $connection;

    public function testAll(): void
    {
        $this->client
            ->expects($this->once())
            ->method('search')
            ->with([
                'body' => [
                    'query' => [
                        'match_all' => new ArrayObject(),
                    ],
                ],
                'from' => 0,
                'size' => 10,
            ])
            ->willReturn([]);

        Model::all();
    }

    public function testAppend(): void
    {
        $model = new Model();
        $model = $model->append('foo');
        self::assertTrue($model->hasAppended('foo'));
    }

    public function testAttributesToArray(): void
    {
        $model = new Model([
            'foo' => 'bar',
        ]);

        self::assertSame(
            $model->toArray(),
            $model->attributesToArray()
        );
    }

    public function testCacheMutatedAttributes(): void
    {
    }

    public function testDelete(): void
    {
        $model = new Model([
            '_id' => 'foo',
        ], true);

        $this->client
            ->expects($this->once())
            ->method('delete')
            ->with([
                'id' => 'foo',
            ])
            ->willReturn([]);

        $model->delete();
    }

    public function testDeleteWillNotFireIfModelDoesNotExist(): void
    {
        $model = new Model([
            '_id' => 'foo',
        ], false);

        $this->client
            ->expects($this->never())
            ->method('delete');

        $model->delete();
    }

    public function testExistsCanBePassedOnConstruction(): void
    {
        $model = new Model([], true);
        self::assertTrue($model->exists());
    }

    public function testExistsIsFalseByDefault(): void
    {
        $model = new Model();
        self::assertFalse($model->exists());
    }

    public function testFillJsonAttribute(): void
    {
    }

    public function testFind(): void
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
                                        '_id' => 'foo',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'from' => 0,
                'size' => 1,
            ]);

        Model::find('foo');
    }

    public function testFromDateTime(): void
    {
    }

    public function testGetAttribute(): void
    {
        $model = new Model([
            'foo' => 'bar',
        ]);

        self::assertSame(
            $model->getAttribute('foo'),
            $model->foo
        );

        self::assertSame(
            $model->getAttribute('foo'),
            'bar'
        );
    }

    public function testGetAttributeReturnsNullForMissingAttributes(): void
    {
        $model = new Model();

        self::assertNull($model->getAttribute('foo'));
    }

    public function testGetAttributeValue(): void
    {
        $model = new Model([
            'foo' => 'bar',
        ]);

        self::assertSame(
            $model->getAttributeValue('foo'),
            'bar'
        );
    }

    public function testGetAttributeValueCallsTransformer(): void
    {
        $model = new class extends Model {
            protected $fillable = ['foo'];

            protected $casts = ['foo' => 'json'];
        };
        $model->setRawAttributes(['foo' => '{"bar":"baz"}']);

        self::assertSame(
            $model->getAttributeValue('foo'),
            ['bar' => 'baz']
        );
    }

    public function testGetAttributes(): void
    {
        $model = new Model([
            'foo' => 'a',
            'bar' => 'b',
            'baz' => 'c',
        ]);

        self::assertSame([
            'foo' => 'a',
            'bar' => 'b',
            'baz' => 'c',
        ], $model->getAttributes());
    }

    public function testGetCasts(): void
    {
    }

    public function testGetChanges(): void
    {
    }

    public function testGetDateFormat(): void
    {
    }

    public function testGetDates(): void
    {
    }

    public function testGetDirty(): void
    {
    }

    public function testGetExcluded(): void
    {
        $model = new class extends Model {
            protected array $excluded = ['foo', 'bar'];
        };

        self::assertSame(['foo', 'bar'], $model->getExcluded());
    }

    public function testGetHighlights(): void
    {
    }

    public function testGetId(): void
    {
        $model = (new Model())->newInstance([], ['_id' => '42'], true);

        self::assertSame('42', $model->getId());
    }

    public function testGetIdReturnsNullIfModelDoesNotExist(): void
    {
        $model = (new Model())->newInstance([], [], false);

        self::assertNull($model->getId());
    }

    public function testGetIncluded(): void
    {
        $model = new class extends Model {
            protected array $included = ['foo', 'bar'];
        };

        self::assertSame(['foo', 'bar'], $model->getIncluded());
    }

    public function testGetKey(): void
    {
        $model = new Model();
        $model->setRawAttributes([
            '_id' => '42',
        ], true);

        self::assertSame('42', $model->getKey());
    }

    public function testGetMutatedAttributes(): void
    {
        $model = new class extends Model {
            public function foo(): Attribute
            {
                return Attribute::get(static fn(int $value) => $value + 1);
            }

            public function bar(): Attribute
            {
                return Attribute::get(static fn(int $value) => $value + 1);
            }
        };

        $model->foo = 1;
        $model->bar = 2;
        $model->baz = 3;

        self::assertSame(
            ['foo', 'bar'],
            $model->getMutatedAttributes()
        );
    }

    public function testGetOriginal(): void
    {
        $model = (new Model())->newInstance(
            ['foo' => 'bar'],
            ['_id' => '42'],
            true
        );

        self::assertSame('bar', $model->foo);

        $model->foo = 'quz';

        self::assertNotSame('bar', $model->foo);
        self::assertSame('bar', $model->getOriginal('foo'));
    }

    public function testGetQueueableConnectionReturnsConnectionName(): void
    {
        $model = new Model();

        self::assertSame(
            $model->getConnectionName(),
            $model->getQueueableConnection()
        );
    }

    public function testGetQueueableId(): void
    {
        $model = new Model([
            '_id' => 'foo',
        ]);

        self::assertSame('foo', $model->getQueueableId());
    }

    public function testGetQueueableRelations(): void
    {
        self::assertEmpty((new Model())->getQueueableRelations());
    }

    public function testGetRawOriginal(): void
    {
        $model = new class(['foo' => 42]) extends Model {
            protected $fillable = ['foo'];

            public function getFooAttribute(): string
            {
                return (string)$this->attributes['foo'];
            }
        };

        $model->syncOriginal();

        $model->foo = 21;

        self::assertSame('21', $model->foo);
        self::assertSame(42, $model->getRawOriginal('foo'));
    }

    public function testGetRelationValue(): void
    {
        $model = new Model();
        self::assertNull($model->getRelationValue('foo'));
    }

    public function testGetRouteKeyName(): void
    {
        $model = new Model();

        self::assertSame('_id', $model->getRouteKeyName());
    }

    public function testGetRouteKeyReturnsId(): void
    {
        $model = new Model(['_id' => '42']);

        self::assertSame('42', $model->getRouteKey());
    }

    public function testGetRouteKeyReturnsValueFromConfiguredField(): void
    {
        $model = new class() extends Model {
            public function getRouteKeyName(): string
            {
                return 'foo';
            }
        };

        $instance = $model->newInstance(
            ['foo' => '42'],
            ['_id' => '24']
        );

        self::assertSame('42', $instance->getRouteKey());
    }

    /**
     * @deprecated
     */
    public function testGetSelectable(): void
    {
        $model = new class extends Model {
            protected array $included = ['foo', 'bar'];
        };

        self::assertSame(['foo', 'bar'], $model->getSelectable());
    }

    /**
     * @deprecated
     */
    public function testGetUnSelectable(): void
    {
        $model = new class extends Model {
            protected array $excluded = ['foo', 'bar'];
        };

        self::assertSame(['foo', 'bar'], $model->getUnSelectable());
    }

    public function testHasCast(): void
    {
        $model = new class extends Model {
            protected $casts = [
                'foo' => 'int',
            ];
        };

        self::assertTrue($model->hasCast('foo'));
        self::assertFalse($model->hasCast('bar'));
    }

    public function testHasGetMutator(): void
    {
    }

    public function testHasSetMutator(): void
    {
    }

    public function testImplementsAllRequiredInterfaces(): void
    {
        $model = new Model();

        self::assertInstanceOf(Arrayable::class, $model);
        self::assertInstanceOf(ArrayAccess::class, $model);
        self::assertInstanceOf(Jsonable::class, $model);
        self::assertInstanceOf(JsonSerializable::class, $model);
        self::assertInstanceOf(UrlRoutable::class, $model);
        self::assertInstanceOf(QueueableEntity::class, $model);
    }

    public function testIndexIsSetOnQuery(): void
    {
        $model = new Model();
        $model->setIndex('foo');
        $query = $model->newQuery();

        self::assertSame('foo', $model->getIndex());
        self::assertSame('foo', $query->getIndex());
    }

    public function testIsClean(): void
    {
        $model = (new Model())->newInstance([
            'foo' => 42,
            'bar' => 'baz',
        ]);

        self::assertTrue($model->isClean());
        self::assertTrue($model->isClean(['foo']));
        self::assertTrue($model->isClean(['bar']));
        self::assertTrue($model->isClean(['foo', 'bar']));

        $model->foo = 43;

        self::assertFalse($model->isClean());
        self::assertFalse($model->isClean(['foo']));
        self::assertTrue($model->isClean(['bar']));
        self::assertFalse($model->isClean(['foo', 'bar']));
    }

    public function testIsDirty(): void
    {
        $model = (new Model())->newInstance([
            'foo' => 'bar',
        ], ['_id' => '42'], true);

        self::assertFalse($model->isDirty());
        self::assertFalse($model->isDirty('foo'));

        $model->foo = 'quz';

        self::assertTrue($model->isDirty());
        self::assertTrue($model->isDirty('foo'));
    }

    public function testIsRelation(): void
    {
        $model = new Model();

        self::assertFalse($model->isRelation('foo'));
    }

    public function testJsonSerialize(): void
    {
    }

    public function testMergeCasts(): void
    {
        $model = new class extends Model {
            protected $casts = [
                'foo' => 'int',
            ];
        };

        self::assertTrue($model->hasCast('foo'));
        self::assertFalse($model->hasCast('bar'));

        $model->mergeCasts([
            'bar' => 'int',
        ]);

        self::assertTrue($model->hasCast('foo'));
        self::assertTrue($model->hasCast('bar'));
    }

    public function testModelIndex(): void
    {
        $model = new Model();
        $model->setIndex('foo');

        self::assertSame('foo', $model->getIndex());
    }

    public function testModelIndexDefaultsToNull(): void
    {
        $model = new Model();

        self::assertNull($model->getIndex());
    }

    public function testModelIndexFromUserlandProp(): void
    {
        $model = new class extends Model {
            protected string|null $index = 'bar';
        };

        self::assertSame('bar', $model->getIndex());

        $model->setIndex('foo');

        self::assertSame('foo', $model->getIndex());
    }

    public function testOffsetExists(): void
    {
        $model = new Model([
            'foo' => 'bar',
        ]);

        self::assertTrue(isset($model->foo));
        self::assertFalse(isset($model->bar));
    }

    public function testOffsetGet(): void
    {
        $model = new Model([
            'foo' => 'bar',
        ]);
        self::assertSame(
            $model->foo,
            $model->getAttribute('foo')
        );
    }

    public function testOffsetSet(): void
    {
        $model = new Model([
            'foo' => 'bar',
        ]);
        self::assertSame($model->foo, 'bar');
        $model->foo = 'quz';
        self::assertSame($model->foo, 'quz');
    }

    public function testOffsetUnset(): void
    {
        $instance = (new Model())->newInstance([
            'foo' => 'bar',
        ]);

        self::assertEquals('bar', $instance->foo);

        unset($instance->foo);

        self::assertNull($instance->foo);
    }

    public function testOnly(): void
    {
        $model = new Model();
        $model->foo = '1';
        $model->bar = '2';
        $model->baz = '3';

        $attributes = $model->only(['foo', 'bar']);

        self::assertSame(
            ['foo' => '1', 'bar' => '2'],
            $attributes
        );
    }

    public function testOriginalIsEquivalent(): void
    {
        $model = new Model([
            'foo' => 'bar',
        ]);

        self::assertFalse($model->originalIsEquivalent('foo'));

        $model->syncOriginal();

        self::assertTrue($model->originalIsEquivalent('foo'));

        $model->foo = 'baz';

        self::assertFalse($model->originalIsEquivalent('foo'));
    }

    public function testRelationsToArray(): void
    {
        $model = new Model();

        self::assertEmpty($model->relationsToArray());
    }

    public function testResolveChildRouteBinding(): void
    {
        $model = (new Model())->newInstance([
            'foo' => 42,
            'bar' => 'baz',
        ]);

        self::assertSame(
            $model->resolveRouteBinding(42),
            $model->resolveChildRouteBinding('', 42, '')
        );
    }

    public function testResolveRouteBinding(): void
    {
        $this->client
            ->expects(self::any())
            ->method('search')
            ->with([
                'from' => 0,
                'size' => 1,
                'body' => [
                    'query' => [
                        'bool' => [
                            'filter' => [
                                [
                                    'term' => [
                                        '_id' => 42,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->willReturn([
                'hits' => [
                    'hits' => [
                        ['_id' => '42'],
                    ],
                ],
            ]);

        $model = (new Model())->resolveRouteBinding(42);

        self::assertSame($model->_id, '42');
    }

    public function testResolveRouteBindingFromField(): void
    {
        $this->client
            ->expects(self::any())
            ->method('search')
            ->with([
                'from' => 0,
                'size' => 1,
                'body' => [
                    'query' => [
                        'bool' => [
                            'filter' => [
                                [
                                    'term' => [
                                        'foo' => 42,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->willReturn([
                'hits' => [
                    'hits' => [
                        ['_id' => '42'],
                    ],
                ],
            ]);

        $model = (new Model())->resolveRouteBinding(42, 'foo');

        self::assertSame($model->_id, '42');
    }

    public function testSaveCreatesNewModel(): void
    {
        $this->client
            ->expects($this->once())
            ->method('index')
            ->willReturn([
                '_id' => '42',
            ]);

        $model = new Model();
        $model->save();

        self::assertSame('42', $model->getId());
    }

    public function testSaveUpdatesExistingModel(): void
    {
        $this->client
            ->expects(self::any())
            ->method('update')
            ->willReturn((object)[
                '_id' => '42',
                'foo' => 'bar',
            ]);

        $model = (new Model())->newInstance(
            ['foo' => 'bar'],
            ['_id' => '42'],
            true
        );
        $model->save();

        self::assertSame('42', $model->getId());
    }

    public function testSetAppends(): void
    {
    }

    public function testSetAttribute(): void
    {
    }

    public function testSetConnection(): void
    {
    }

    public function testSetDateFormat(): void
    {
    }

    public function testSetRawAttributes(): void
    {
        $model = new class extends Model {
            protected $fillable = ['foo'];

            protected $casts = ['foo' => 'json'];
        };
        $model->setRawAttributes(['foo' => '{"bar":"baz"}']);

        self::assertSame(
            $model->getAttributeValue('foo'),
            ['bar' => 'baz']
        );
    }

    public function testSyncChanges(): void
    {
        $model = (new Model())->newInstance([
            'foo' => 42,
            'bar' => 'baz',
        ]);

        $model->syncChanges();
        self::assertEmpty($model->getChanges());

        $model->foo = 43;
        self::assertEmpty($model->getChanges());
        $model->syncChanges();
        self::assertNotEmpty($model->getChanges());
    }

    public function testSyncOriginal(): void
    {
    }

    public function testSyncOriginalAttribute(): void
    {
    }

    public function testSyncOriginalAttributes(): void
    {
    }

    public function testToArray(): void
    {
    }

    public function testToJson(): void
    {
    }

    public function testWasChanged(): void
    {
    }

    protected function getPackageProviders($app): array
    {
        return [
            ElasticsearchServiceProvider::class,
        ];
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
}
