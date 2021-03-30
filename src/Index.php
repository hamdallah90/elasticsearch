<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch;

use ArrayObject;
use Elasticsearch\Client;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Stringable;
use TypeError;

use function array_unique;
use function count;
use function is_array;
use function is_string;

/**
 * Index
 *
 * Abstracts a single Elasticsearch index.
 *
 * @bundle Matchory\Elasticsearch
 */
class Index implements Stringable
{
    private const ALIASES = 'aliases';

    private const BODY = 'body';

    private const CLIENT = 'client';

    private const CLIENT_IGNORE = 'ignore';

    private const INDEX = 'index';

    private const MAPPINGS = 'mappings';

    private const SETTINGS = 'settings';

    private const SETTINGS_NUMBER_OF_REPLICAS = 'number_of_replicas';

    private const SETTINGS_NUMBER_OF_SHARDS = 'number_of_shards';

    /**
     * Aliases the index shall be configured with.
     *
     * @var array<string, array<string, mixed>|string|ArrayObject>
     */
    private array $aliases = [];

    /**
     * Ignored HTTP errors
     *
     * @var string[]
     */
    private array $ignores = [];

    /**
     * Mappings the index shall be configured with.
     */
    private array $mappings = [];

    /**
     * The number of replicas the index shall be configured with.
     */
    private int|null $replicas = null;

    /**
     * The number of shards the index shall be configured with.
     */
    private int|null $shards = null;

    /**
     * Creates a new index instance.
     *
     * @param ConnectionInterface $connection    Elasticsearch connection to use.
     * @param string              $name          Name of the index to create.
     *                                           Index names must meet the
     *                                           following criteria:
     *                                           - Lowercase only
     *                                           - Cannot include \, /, *, ?, ",
     *                                           <, >, |, ` ` (space character),
     *                                           `,`, or #
     *                                           - Indices prior to 7.0 could
     *                                           contain a colon (:), but that’s
     *                                           been deprecated and won’t be
     *                                           supported in 7.0+
     *                                           - Cannot start with -, _, +
     *                                           - Cannot be `.` or `..`
     *                                           - Cannot be longer than 255
     *                                           bytes (note it is bytes, so
     *                                           multibyte characters will
     *                                           count towards the 255 limit
     *                                           faster)
     *                                           - Names starting with `.` are
     *                                           deprecated, except for hidden
     *                                           indices and internal indices
     *                                           managed by plugins
     */
    public function __construct(
        private ConnectionInterface $connection,
        private readonly string $name
    ) {
    }

    /**
     * Retrieves the Elasticsearch  client instance.
     *
     * @return Client
     * @internal
     */
    public function getClient(): Client
    {
        return $this->getConnection()->getClient();
    }

    /**
     * Retrieves the active connection.
     *
     * @return ConnectionInterface
     * @internal
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Retrieves the name of the new index.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the active connection on the index.
     *
     * @param ConnectionInterface $connection
     *
     * @internal
     */
    public function setConnection(ConnectionInterface $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * @inheritdoc
     */
    public function __toString(): string
    {
        return $this->getName();
    }

    /**
     * An index alias is a secondary name used to refer to one or more existing
     * indices. Most Elasticsearch APIs accept an index alias in place of
     * an index.
     *
     * APIs in Elasticsearch accept an index name when working against a
     * specific index, and several indices when applicable. The index aliases
     * API allows aliasing an index with a name, with all APIs automatically
     * converting the alias name to the actual index name. An alias can also be
     * mapped to more than one index, and when specifying it, the alias will
     * automatically expand to the aliased indices. An alias can also be
     * associated with a filter that will automatically be applied when
     * searching, and routing values. An alias cannot have the same name as
     * an index.
     *
     * @param string                        $alias   Name of the alias to add.
     * @param ArrayObject|array|string|null $options Options to pass to
     *                                               the alias.
     *
     * @return $this
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/master/indices-aliases.html
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-create-index.html#create-index-aliases
     */
    public function alias(
        string $alias,
        ArrayObject|array|string $options = null
    ): self {
        if (
            $options !== null &&
            ! is_string($options) &&
            ! is_array($options)
        ) {
            throw new TypeError(
                'Alias options may be passed as an array, a string ' .
                'routing key, or literal null.'
            );
        }

        $this->aliases[$alias] = $options ?? new ArrayObject();

        return $this;
    }

    public function count(): int
    {
        return $this->getConnection()->newQuery()->count();
    }

    /**
     * Creates a new index.
     *
     * You can use the create index API to add a new index to an Elasticsearch
     * cluster. When creating an index, you can specify the following:
     *  - Settings for the index
     *  - Mappings for fields in the index
     *  - Index aliases
     *
     *
     *
     * @return array{
     *     acknowledged: bool,
     *     shards_acknowledged: bool,
     *     index: string
     * }
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-create-index.html
     */
    public function create(): array
    {
        $body = [];
        $settings = [];

        if ($this->shards !== null) {
            $settings[self::SETTINGS_NUMBER_OF_SHARDS] = $this->shards;
        }

        if ($this->replicas !== null) {
            $settings[self::SETTINGS_NUMBER_OF_REPLICAS] = $this->replicas;
        }

        if ($settings) {
            $body[self::SETTINGS] = $settings;
        }

        $params = [
            self::INDEX => $this->name,
            self::BODY => $body,
        ];

        if (count($this->ignores) > 0) {
            $params[self::CLIENT] = [
                self::CLIENT_IGNORE => $this->ignores,
            ];
        }

        if (count($this->aliases) > 0) {
            $params[self::BODY][self::ALIASES] = $this->aliases;
        }

        if (count($this->mappings) > 0) {
            $params[self::BODY][self::MAPPINGS] = $this->mappings;
        }

        return $this
            ->getConnection()
            ->getClient()
            ->indices()
            ->create($params);
    }

    /**
     * Deletes an existing index.
     *
     * Deleting an index deletes its documents, shards, and metadata.
     * It does not delete related Kibana components, such as data views,
     * visualizations, or dashboards.
     *
     * You cannot delete the current write index of a data stream. To delete the
     * index, you must roll over the data stream so a new write index is
     * created. You can then use the `delete index API` to delete the previous
     * write index.
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-delete-index.html
     */
    public function drop(): void
    {
        $parameters = [
            self::INDEX => $this->name,
        ];

        if (count($this->ignores) > 0) {
            $parameters[self::CLIENT] = [
                self::CLIENT_IGNORE => $this->ignores,
            ];
        }

        $this
            ->getConnection()
            ->getClient()
            ->indices()
            ->delete($parameters);
    }

    /**
     * Checks whether an index exists.
     *
     * @return bool
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-exists.html#indices-exists
     */
    public function exists(): bool
    {
        return $this
            ->getConnection()
            ->getClient()
            ->indices()
            ->exists([
                'index' => $this->name,
            ]);
    }

    /**
     * Configures the client to ignore bad HTTP requests.
     *
     * @param int ...$statusCodes HTTP Status codes to ignore.
     *
     * @return $this
     */
    public function ignores(int ...$statusCodes): self
    {
        $this->ignores = array_unique($statusCodes);

        return $this;
    }

    /**
     * Sets the fields mappings.
     *
     * @param array $mappings
     *
     * @return $this
     */
    public function mapping(array $mappings = []): self
    {
        $this->mappings = $mappings;

        return $this;
    }

    /**
     * The number of replicas each primary shard has. Defaults to `1`.
     *
     * @param int $replicas Number of replicas to configure.
     *
     * @return $this
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/master/index-modules.html#index-number-of-replicas
     */
    public function replicas(int $replicas): self
    {
        $this->replicas = $replicas;

        return $this;
    }

    /**
     * The number of primary shards that an index should have. Defaults to `1`.
     * This setting can only be set at index creation time. It cannot be changed
     * on a closed index.
     *
     * The number of shards are limited to 1024 per index. This limitation is a
     * safety limit to prevent accidental creation of indices that can
     * destabilize a cluster due to resource allocation. The limit can be
     * modified by specifying
     * `export ES_JAVA_OPTS="-Des.index.max_number_of_shards=128"` system
     * property on every node that is part of the cluster.
     *
     * @param int $shards Number of shards to configure.
     *
     * @return $this
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/master/index-modules.html#index-number-of-shards
     */
    public function shards(int $shards): self
    {
        $this->shards = $shards;

        return $this;
    }
}


