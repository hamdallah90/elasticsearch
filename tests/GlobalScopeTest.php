<?php

/**
 * This file is part of elasticsearch, a Matchory application.
 *
 * Unauthorized copying of this file, via any medium, is strictly prohibited.
 * Its contents are strictly confidential and proprietary.
 *
 * @copyright 2020–2021 Matchory GmbH · All rights reserved
 * @author    Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests;

use InvalidArgumentException;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Matchory\Elasticsearch\Model;
use Matchory\Elasticsearch\Query;
use Matchory\Elasticsearch\Tests\Traits\ESQueryTrait;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

class GlobalScopeTest extends TestCase
{
    use ESQueryTrait;

    /**
     * @test
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function getGlobalScope(): void
    {
        $model = new class extends Model {
            public static $connection = null;

            public static function resolveConnection(
                ?string $connection = null
            ): ConnectionInterface {
                return static::$connection;
            }
        };
        $model::$connection = $this->getConnection();

        $scope = static function (Query $query): void {
        };

        $model::addGlobalScope('foo', $scope);

        self::assertSame($scope, $model::getGlobalScope(
            'foo'
        ));

        self::assertNull($model::getGlobalScope('bar'));
    }

    /**
     * @test
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     * @throws Exception
     */
    public function getGlobalScopes(): void
    {
        $model = new class extends Model {
            public static $connection = null;

            public static function resolveConnection(
                ?string $connection = null
            ): ConnectionInterface {
                return static::$connection;
            }
        };
        $model::$connection = $this->getConnection();

        $scope = static function (Query $query): void {
        };

        $model::addGlobalScope('foo', $scope);

        self::assertContains($scope, $model->getGlobalScopes());
    }

    /**
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @test
     */
    public function addGlobalScope(): void
    {
        self::assertEquals(
            $this->getExpected('views', 500),
            $this->getActual('views', 500)
        );
    }

    /**
     * @test
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public function hasGlobalScope(): void
    {
        $model = new class extends Model {
            public static $connection = null;

            public static function resolveConnection(
                ?string $connection = null
            ): ConnectionInterface {
                return static::$connection;
            }
        };
        $model::$connection = $this->getConnection();
        $model::addGlobalScope('foo', static function (
            Query $query
        ) {
        });

        self::assertTrue($model::hasGlobalScope('foo'));
        self::assertFalse($model::hasGlobalScope('bar'));
    }

    /**
     * @test
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     * @throws Exception
     */
    public function withoutGlobalScope(): void
    {
        $model = new class extends Model {
            public static $connection = null;

            public static function resolveConnection(
                ?string $connection = null
            ): ConnectionInterface {
                return static::$connection;
            }
        };
        $model::$connection = $this->getConnection();

        $scope = static function (Query $query): void {
        };
        $model::addGlobalScope('foo', $scope);

        self::assertTrue($model::hasGlobalScope('foo'));
        $query = $model->newQuery();
        self::assertNotContains('foo', $query->removedScopes());
        $query = $query->withoutGlobalScope('foo');
        self::assertContains('foo', $query->removedScopes());
    }

    /**
     * @test
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     * @throws Exception
     */
    public function withoutGlobalScopes(): void
    {
        $model = new class extends Model {
            public static $connection = null;

            public static function resolveConnection(
                ?string $connection = null
            ): ConnectionInterface {
                return static::$connection;
            }
        };
        $model::$connection = $this->getConnection();

        $foo = static function (Query $query): void {
        };
        $bar = static function (Query $query): void {
        };
        $model::addGlobalScope('foo', $foo);
        $model::addGlobalScope('bar', $bar);

        self::assertTrue($model::hasGlobalScope('foo'));
        $query = $model->newQuery();
        self::assertNotContains('foo', $query->removedScopes());
        self::assertNotContains('bar', $query->removedScopes());
        $query = $query->withoutGlobalScopes();
        self::assertContains('foo', $query->removedScopes());
        self::assertContains('bar', $query->removedScopes());
    }

    /**
     * @param      $name
     * @param      $value
     *
     * @return array
     * @throws InvalidArgumentException
     */
    protected function getActual($name, $value): array
    {
        $model = new class extends Model {
            public static $connection = null;

            public static function resolveConnection(
                ?string $connection = null
            ): ConnectionInterface {
                return static::$connection;
            }
        };
        $model::$connection = $this->getConnection();
        $model::addGlobalScope('foo', function (
            Query $query
        ) use ($name, $value) {
            return $query->where($name, $value);
        });

        return $this->getQueryObject($model->newQuery())->toArray();
    }

    protected function getExpected($name, $value): array
    {
        return $this->getQueryArray([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'term' => [
                                $name => $value,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
