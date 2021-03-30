<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Facades;

use Illuminate\Support\Facades\Facade;
use Matchory\Elasticsearch\Collection;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use Matchory\Elasticsearch\Model;
use Matchory\Elasticsearch\Pagination;
use Matchory\Elasticsearch\Builder;

/**
 * Elasticsearch Facade
 * ====================
 * This facade proxies to the default connection instance, which in turn proxies
 * to a new query instance. This provides unified access to almost all methods
 * the library has to offer.
 *
 * @method static ConnectionInterface connection(string|null $name = null)
 * @method static string getDefaultConnection()
 * @method static void setDefaultConnection(string $name)
 * @method static Builder newQuery()
 * @method static Builder index(string $index)
 * @method static Builder scroll(string $scroll)
 * @method static Builder scrollId(string|null $scroll)
 * @method static Builder searchType(string $type)
 * @method static Builder ignore(...$args)
 * @method static Builder orderBy($field, string $direction = 'asc')
 * @method static Builder select(...$args)
 * @method static Builder unselect(...$args)
 * @method static Builder where($name, $operator = Builder::OPERATOR_EQUAL, $value = null)
 * @method static Builder firstWhere($name, $operator = Builder::OPERATOR_EQUAL, $value = null)
 * @method static Builder whereNot($name, $operator = Builder::OPERATOR_EQUAL, $value = null)
 * @method static Builder whereBetween($name, $firstValue, $lastValue)
 * @method static Builder whereNotBetween($name, $firstValue, $lastValue)
 * @method static Builder whereIn($name, array $values)
 * @method static Builder whereNotIn($name, array $values)
 * @method static Builder whereExists($name, bool $exists = true)
 * @method static Builder distance($name, $value, string $distance)
 * @method static Builder search(string|null $queryString = null, $settings = null, int|null $boost = null)
 * @method static Builder nested(string $path)
 * @method static Builder highlight(...$args)
 * @method static Builder body(array $body = [])
 * @method static Builder groupBy(string $field)
 * @method static Builder id(string|null $id = null)
 * @method static Builder skip(int $from = 0)
 * @method static Builder take(int $size = 10)
 * @method static Builder withGlobalScope(string $identifier, $scope)
 * @method static Builder withoutGlobalScope($scope)
 * @method static Builder withoutGlobalScopes(array $scopes = null)
 * @method static array removedScopes()
 * @method static bool  hasNamedScope(string $scope)
 * @method static Builder scopes($scopes)
 * @method static Builder applyScopes()
 * @method static Builder remember($ttl, string|null $key = null)
 * @method static Builder rememberForever(string|null $key = null)
 * @method static Collection get(string|null $scrollId = null)
 * @method static Pagination paginate(int $perPage = 10, string $pageName = 'page', int|null $page = null)
 * @method static Model|null first(string|null $scrollId = null)
 * @method static Model|mixed|null firstOr(string|null $scrollId = null, callable|null $callback = null)
 * @method static Model firstOrFail(string|null $scrollId = null)
 * @method static object insert($data, string|null $id = null)
 * @method static object update($data, string|null $id = null)
 * @method static object delete(string|null $id = null)
 * @method static int count()
 * @method static object script($script, array $params = [])
 * @method static object increment(string $field, int $count = 1)
 * @method static object decrement(string $field, int $count = 1)
 * @method static array|null performSearch(string|null $scrollId = null)
 * @method static ConnectionInterface getConnection()
 * @method static array createIndex(string $name, callable|null $callback = null)
 * @method static array dropIndex(string $name)
 *
 * @package Matchory\Elasticsearch\Facades
 */
class ES extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getFacadeAccessor(): string
    {
        return ConnectionResolverInterface::class;
    }
}
