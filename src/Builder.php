<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch;

use ArrayIterator;
use BadMethodCallException;
use Elasticsearch\Client;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Traits\ForwardsCalls;
use IteratorAggregate;
use JsonException;
use JsonSerializable;
use Matchory\Elasticsearch\Concerns\AppliesScopes;
use Matchory\Elasticsearch\Concerns\BuildsFluentQueries;
use Matchory\Elasticsearch\Concerns\ExecutesQueries;
use Matchory\Elasticsearch\Concerns\ExplainsQueries;
use Matchory\Elasticsearch\Concerns\ManagesIndices;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Psr\SimpleCache\InvalidArgumentException;

use function count;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Query builder instance for Elasticsearch queries
 *
 * @package  Matchory\Elasticsearch\Query
 * @template T of Model
 */
class Builder implements Arrayable, JsonSerializable, Jsonable, IteratorAggregate
{
    use AppliesScopes;

    /**
     * @uses BuildsFluentQueries<T>
     * @use  BuildsFluentQueries<T>
     */
    use BuildsFluentQueries;

    /**
     * @uses ExecutesQueries<T>
     * @use  ExecutesQueries<T>
     */
    use ExecutesQueries;
    use ForwardsCalls;
    use ManagesIndices;
    use ExplainsQueries;

    public const DEFAULT_CACHE_PREFIX = 'es';

    public const DEFAULT_LIMIT = 10;

    public const DEFAULT_OFFSET = 0;

    public const EQ = self::OPERATOR_EQUAL;

    public const EXISTS = self::OPERATOR_EXISTS;

    public const GT = self::OPERATOR_GREATER_THAN;

    public const GTE = self::OPERATOR_GREATER_THAN_OR_EQUAL;

    public const LIKE = self::OPERATOR_LIKE;

    public const LT = self::OPERATOR_LOWER_THAN;

    public const LTE = self::OPERATOR_LOWER_THAN_OR_EQUAL;

    public const NEQ = self::OPERATOR_NOT_EQUAL;

    public const OPERATOR_EQUAL = '=';

    public const OPERATOR_EXISTS = 'exists';

    public const OPERATOR_GREATER_THAN = '>';

    public const OPERATOR_GREATER_THAN_OR_EQUAL = '>=';

    public const OPERATOR_LIKE = 'like';

    public const OPERATOR_LOWER_THAN = '<';

    public const OPERATOR_LOWER_THAN_OR_EQUAL = '<=';

    public const OPERATOR_NOT_EQUAL = '!=';

    public const REGEXP_FLAG_ALL = 1;

    public const REGEXP_FLAG_ANYSTRING = 32;

    public const REGEXP_FLAG_COMPLEMENT = 4;

    public const REGEXP_FLAG_INTERSECTION = 16;

    public const REGEXP_FLAG_INTERVAL = 8;

    public const REGEXP_FLAG_NONE = 2;

    private static array $defaultSource = [
        'includes' => [],
        'excludes' => [],
    ];

    /**
     * Elasticsearch connection instance
     *
     * This connection instance will receive any unresolved method calls from
     * the query, effectively acting as a proxy: The connection itself proxies
     * to the Elasticsearch client instance.
     */
    private ConnectionInterface $connection;

    /**
     * Elasticsearch model instance.
     *
     * @var T
     */
    private Model $model;

    /**
     * Creates a new query builder instance.
     *
     * @param ConnectionInterface $connection Elasticsearch connection the query
     *                                        builder uses.
     */
    public function __construct(
        ConnectionInterface $connection,
        Model|null $model = null
    ) {
        $this->connection = $connection;

        // We set a plain model here so there's always a model instance set.
        // This avoids errors in methods that rely on a model.
        /** @var T $model */
        $model = $model ?? new Model();
        $this->model = $model;
    }

    /**
     * Retrieves the underlying Elasticsearch connection.
     *
     * @return ConnectionInterface Connection instance.
     * @see ConnectionInterface
     * @see Connection
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Proxies to the collection iterator, allowing to iterate the query builder
     * directly as though it were a result collection.
     *
     * @inheritDoc
     * @throws InvalidArgumentException
     */
    final public function getIterator(): ArrayIterator
    {
        return $this->get()->getIterator();
    }

    /**
     * Retrieves the instance of the model the query is scoped to. It is set to
     * the model that initiated a query, but defaults to the Model class itself
     * if the query builder is used without models.
     *
     * @return T Model instance used for the current query.
     * @internal
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Sets the model the query is based on. Any results will be casted to this
     * model. If no model is set, a plain model instance will be used.
     *
     * @param T $model Model to use for the current query.
     *
     * @return $this Query builder instance for chaining.
     * @internal This method will be called by the model automatically.
     */
    public function setModel(Model $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Forwards calls to the model instance. If the called method is a scope,
     * it will be applied to the query.
     *
     * @param string $method     Name of the called method.
     * @param array  $parameters Parameters passed to the method.
     *
     * @return $this Query builder instance.
     * @throws BadMethodCallException
     */
    public function __call(string $method, array $parameters): self
    {
        if ($this->hasNamedScope($method)) {
            return $this->callNamedScope($method, $parameters);
        }

        return $this->forwardCallTo(
            $this->getModel(),
            $method,
            $parameters
        );
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Retrieves the underlying Elasticsearch client instance. This can be used
     * to work with the Elasticsearch library directly. You should check out its
     * documentation for more information.
     *
     * @return Client Elasticsearch Client instance.
     * @see https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/overview.html
     * @see Client
     */
    public function raw(): Client
    {
        return $this->getConnection()->getClient();
    }

    /**
     * Converts the fluent query into an Elasticsearch query array that can be
     * converted into JSON.
     *
     * @inheritDoc
     */
    final public function toArray(): array
    {
        return (clone $this)->buildQuery();
    }

    /**
     * Converts the query to a JSON string.
     *
     * @inheritDoc
     * @throws JsonException
     */
    public function toJson($options = 0): string
    {
        return json_encode(
            $this->jsonSerialize(),
            JSON_THROW_ON_ERROR | $options
        );
    }

    /**
     * Converts the query into an Elasticsearch query array.
     *
     * @return array
     */
    protected function buildQuery(): array
    {
        $query = $this->applyScopes();

        $parameters = [
            'body' => $query->buildBody(),
            'from' => $query->getSkip(),
            'size' => $query->getSize(),
        ];

        if (count($query->getIgnores())) {
            $parameters['client'] = [
                'ignore' => $query->ignores,
            ];
        }

        if ($searchType = $query->getSearchType()) {
            $parameters['search_type'] = $searchType;
        }

        if ($scroll = $query->getScroll()) {
            $parameters['scroll'] = $scroll;
        }

        if ($index = $query->getIndex()) {
            $parameters['index'] = $index;
        }

        return $parameters;
    }
}
