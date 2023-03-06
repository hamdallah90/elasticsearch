<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Concerns;

use DateTime;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request;
use JsonException;
use Matchory\Elasticsearch\Builder;
use Matchory\Elasticsearch\Bulk;
use Matchory\Elasticsearch\Collection;
use Matchory\Elasticsearch\Exceptions\DocumentNotFoundException;
use Matchory\Elasticsearch\Model;
use Matchory\Elasticsearch\Pagination;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;

use function array_diff_key;
use function array_flip;
use function array_map;
use function count;
use function get_class;
use function is_callable;
use function is_null;
use function md5;
use function serialize;

use const PHP_SAPI;

/**
 * @template T of Model
 */
trait ExecutesQueries
{
    /**
     * The key that should be used when caching the query.
     *
     * @var string|null
     */
    protected string|null $cacheKey = null;

    /**
     * A cache prefix.
     *
     * @var string
     */
    protected string $cachePrefix = Builder::DEFAULT_CACHE_PREFIX;

    /**
     * The number of seconds to cache the query.
     *
     * @var DateTime|int|null
     */
    protected int|null|DateTime $cacheTtl = null;

    /**
     * Get the collection of results
     *
     * @param string|null $scrollId
     *
     * @return Collection<T>
     * @throws InvalidArgumentException
     */
    public function get(string|null $scrollId = null): Collection
    {
        $result = $this->getResult($scrollId);

        if ( ! $result) {
            return new Collection([]);
        }

        return $this->transformIntoCollection($result);
    }

    /**
     * Get a unique cache key for the complete query.
     *
     * @return string
     */
    public function getCacheKey(): string
    {
        $cacheKey = $this->cacheKey ?: $this->generateCacheKey();

        return "{$this->cachePrefix}.{$cacheKey}";
    }

    /**
     * Performs multiple indexing or delete operations in a single API call.
     * This reduces overhead and can greatly increase indexing speed.
     *
     * > If the Elasticsearch security features are enabled, you must have the
     * > following index privileges for the target data stream, index, or
     * > index alias:
     * >  - To use the create action, you must have the `create_doc`, `create`,
     * >    `index`, or `write` index privilege. Data streams support only the
     * >    `create` action.
     * >  - To use the index action, you must have the `create`, `index`, or
     * >    `write` index privilege.
     * >  - To use the delete action, you must have the `delete` or `write`
     * >    `index` privilege.
     * >  - To use the update action, you must have the `index` or `write`
     * >    `index` privilege.
     * >  - To automatically create a data stream or index with a bulk
     * >    API request, you must have the `auto_configure`, `create_index`, or
     * >    `manage` index privilege. Automatic data stream creation requires a
     * >    matching index template with data stream enabled.
     * >    See Set up a data stream.
     *
     * Optimistic concurrency control
     * ------------------------------
     * Each index and delete action within a bulk API call may include the
     * if_seq_no and if_primary_term parameters in their respective action and
     * meta data lines. The if_seq_no and if_primary_term parameters control how
     * operations are executed, based on the last modification to
     * existing documents. See Optimistic concurrency control for more details.
     *
     * Versioning
     * ----------
     * Each bulk item can include the version value using the version field.
     * It automatically follows the behavior of the index / delete operation
     * based on the _version mapping.
     * It also supports the version_type (see versioning).
     *
     * Routing
     * -------
     * Each bulk item can include the routing value using the routing field.
     * It automatically follows the behavior of the index / delete operation
     * based on the _routing mapping.
     *
     * Data streams do not support custom routing unless they were created wit
     * h the allow_custom_routing setting enabled in the template.
     *
     * Wait for active shards
     * ----------------------
     * When making bulk calls, you can set the wait_for_active_shards parameter
     * to require a minimum number of shard copies to be active before starting
     * to process the bulk request.
     *
     * Refresh
     * -------
     * Control when the changes made by this request are visible to search.
     *
     * Only the shards that receive the bulk request will be affected
     * by refresh. Imagine a _bulk?refresh=wait_for request with three documents
     * in it that happen to be routed to different shards in an index with five
     * shards. The request will only wait for those three shards to refresh.
     * The other two shards that make up the index do not participate in the
     * _bulk request at all.
     *
     * @param callable|array<string, array<string, mixed>> $data Dictionary of
     *                                                           [id => data]
     *                                                           pairs
     *
     * @return array{
     *     took: int,
     *     errors: bool,
     *     items: array<int, array{
     *          create: null|array{
     *              _index: string,
     *              _id: string,
     *              _version: int,
     *              result: string,
     *              _shards: array{
     *                  total: int,
     *                  failed: int,
     *                  successful: int
     *              },
     *              _seq_no: int,
     *              _primary_term: int,
     *              status: int,
     *              error: null|array{
     *                  type: string,
     *                  reason: string,
     *                  index_uuid: string,
     *                  shard: string,
     *                  index: string,
     *              },
     *          },
     *          delete: null|array{
     *              _index: string,
     *              _id: string,
     *              _version: int,
     *              result: string,
     *              _shards: array{
     *                  total: int,
     *                  failed: int,
     *                  successful: int
     *              },
     *              _seq_no: int,
     *              _primary_term: int,
     *              status: int,
     *              error: null|array{
     *                  type: string,
     *                  reason: string,
     *                  index_uuid: string,
     *                  shard: string,
     *                  index: string,
     *              },
     *          },
     *          index: null|array{
     *              _index: string,
     *              _id: string,
     *              _version: int,
     *              result: string,
     *              _shards: array{
     *                  total: int,
     *                  failed: int,
     *                  successful: int
     *              },
     *              _seq_no: int,
     *              _primary_term: int,
     *              status: int,
     *              error: null|array{
     *                  type: string,
     *                  reason: string,
     *                  index_uuid: string,
     *                  shard: string,
     *                  index: string,
     *              },
     *          },
     *          update: null|array{
     *              _index: string,
     *              _id: string,
     *              _version: int,
     *              result: string,
     *              _shards: array{
     *                  total: int,
     *                  failed: int,
     *                  successful: int
     *              },
     *              _seq_no: int,
     *              _primary_term: int,
     *              status: int,
     *              error: null|array{
     *                  type: string,
     *                  reason: string,
     *                  index_uuid: string,
     *                  shard: string,
     *                  index: string,
     *              },
     *          },
     *     }>
     * }
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/docs-bulk.html
     */
    public function bulk(callable|array $data): array
    {
        if (is_callable($data)) {
            $bulk = new Bulk($this);

            $data($bulk);

            $parameters = $bulk->body();
        } else {
            $parameters = [];

            // Note that bulk operations in Elasticsearch use a special format
            // that is divided into operation metadata (ID, index etc.) and
            // attributes to be inserted, the latter following the former.
            // The below sequential array keys are thus NOT a mistake.
            foreach ($data as $id => $attributes) {
                $parameters['body'][] = [
                    'index' => [
                        '_index' => $this->getIndex(),
                        '_id' => $id,
                    ],
                ];

                $parameters['body'][] = $attributes;
            }
        }

        return $this
            ->getConnection()
            ->getClient()
            ->bulk($parameters);
    }

    /**
     * Set the cache prefix.
     *
     * @param string $prefix
     *
     * @return $this
     */
    public function cachePrefix(string $prefix): self
    {
        $this->cachePrefix = $prefix;

        return $this;
    }

    /**
     * Clear scroll query id
     *
     * @param string|null $scrollId
     *
     * @return array
     */
    public function clear(string|null $scrollId = null): array
    {
        $scrollId = $scrollId ?? $this->getScrollId();

        if ( ! $scrollId) {
            return [];
        }

        $parameters = [
            'body' => [$scrollId],
        ];

        if (count($ignores = $this->getIgnores())) {
            $parameters['client'] = [
                'ignore' => $ignores,
            ];
        }

        return $this
            ->getConnection()
            ->getClient()
            ->clearScroll($parameters);
    }

    /**
     * Get the count of result
     *
     * @return int
     */
    public function count(): int
    {
        $query = $this->toArray();

        // Remove unsupported count query keys
        unset(
            $query['size'],
            $query['from'],
            $query['body']['_source'],
            $query['body']['sort']
        );

        $response = $this
            ->getConnection()
            ->getClient()
            ->count($query);

        return (int)$response['count'];
    }

    /**
     * Increment a document field
     *
     * @param string $field
     * @param int    $count
     *
     * @return array{
     *   _shards: array{
     *      total: int,
     *      failed: int,
     *      successful: int
     *   },
     *   _index: string,
     *   _id: string,
     *   _version: int,
     *   _primary_term: int,
     *   _seq_no: int,
     *   result: string
     * }
     */
    public function decrement(string $field, int $count = 1): array
    {
        return $this->script("ctx._source.{$field} -= params.count", [
            'count' => $count,
        ]);
    }

    /**
     * Removes a document from the specified index.
     *
     * You use DELETE to remove a document from an index. You must specify the
     * index name and document ID.
     *
     * Optimistic concurrency control
     * ------------------------------
     * Delete operations can be made conditional and only be performed if the
     * last modification to the document was assigned the sequence number and
     * primary term specified by the if_seq_no and if_primary_term parameters.
     * If a mismatch is detected, the operation will result in a
     * VersionConflictException and a status code of 409. See Optimistic
     * concurrency control for more details.
     *
     * Versioning
     * ----------
     * Each document indexed is versioned. When deleting a document, the version
     * can be specified to make sure the relevant document we are trying to
     * delete is actually being deleted, and it has not changed in the meantime.
     * Every write operation executed on a document, deletes included, causes
     * its version to be incremented. The version number of a deleted document
     * remains available for a short time after deletion to allow for control of
     * concurrent operations. The length of time for which a deleted document's
     * version remains available is determined by the index.gc_deletes index
     * setting and defaults to 60 seconds.
     *
     * Automatic index creation
     * ------------------------
     * If an external versioning variant is used, the delete operation
     * automatically creates the specified index if it does not exist. For
     * information about manually creating indices, see create index API.
     *
     * Distributed
     * -----------
     * The delete operation gets hashed into a specific shard id. It then gets
     * redirected into the primary shard within that id group, and replicated
     * (if needed) to shard replicas within that id group.
     *
     * Wait for active shards
     * ----------------------
     * When making delete requests, you can set the wait_for_active_shards
     * parameter to require a minimum number of shard copies to be active before
     * starting to process the delete request. See here for further details and
     * a usage example.
     *
     * Refresh
     * -------
     * Control when the changes made by this request are visible to search.
     *
     * Timeout
     * -------
     * The primary shard assigned to perform the delete operation might not be
     * available when the delete operation is executed. Some reasons for this
     * might be that the primary shard is currently recovering from a store or
     * undergoing relocation. By default, the delete operation will wait on the
     * primary shard to become available for up to 1 minute before failing and
     * responding with an error. The timeout parameter can be used to explicitly
     * specify how long it waits.
     *
     * > If the Elasticsearch security features are enabled, you must have the
     * > `delete` or `write` index privilege for the target index or index alias
     *
     * @param string|null $id
     * @param array{
     *     if_seq_no: int|null,
     *     if_primary_term: int|null,
     *     refresh: bool|string|null,
     *     routing: string|null,
     *     timeout: string|null,
     *     version: int|null,
     *     version_type: string|null,
     *     wait_for_active_shards: bool|null
     * }|null             $parameters
     *
     * @return array{
     *   _shards: array{
     *      total: int,
     *      failed: int,
     *      successful: int
     *   },
     *   _index: string,
     *   _id: string,
     *   _version: int,
     *   _primary_term: int,
     *   _seq_no: int,
     *   result: string
     * }
     */
    public function delete(
        string|null $id = null,
        array|null $parameters = null,
    ): array {
        if ($id) {
            $this->id($id);
        }

        $parameters = $parameters ?? [];
        $parameters['id'] = $this->getId();

        if (count($ignores = $this->getIgnores())) {
            $parameters['client'] = [
                ...($parameters['client'] ?? []),
                'ignore' => $ignores,
            ];
        }

        $parameters = $this->addBaseParams($parameters);

        return $this
            ->getConnection()
            ->getClient()
            ->delete($parameters);
    }

    /**
     * Get the first result
     *
     * @param string|null $scrollId
     *
     * @return T|null
     * @throws InvalidArgumentException
     */
    public function first(string|null $scrollId = null): Model|null
    {
        $this->take(1);

        $result = $this->getResult($scrollId);

        if ( ! $result) {
            return null;
        }

        return $this->transformIntoModel($result);
    }

    /**
     * Get the first result or call a callback.
     *
     * @param string|null   $scrollId
     * @param callable|null $callback
     *
     * @return T|null
     * @throws InvalidArgumentException
     */
    public function firstOr(
        string|null $scrollId = null,
        callable|null $callback = null
    ): Model|null {
        if (is_callable($scrollId)) {
            $callback = $scrollId;
            $scrollId = null;
        }

        if ( ! is_null($model = $this->first($scrollId))) {
            return $model;
        }

        return $callback ? $callback() : null;
    }

    /**
     * Get the first result or fail.
     *
     * @param string|null $scrollId
     *
     * @return T
     * @throws DocumentNotFoundException
     * @throws InvalidArgumentException
     */
    public function firstOrFail(string|null $scrollId = null): Model
    {
        if ( ! is_null($model = $this->first($scrollId))) {
            return $model;
        }

        $id = $this->getId();

        throw (new DocumentNotFoundException())->setModel(
            get_class($this->getModel()),
            $id ? (array)$id : []
        );
    }

    /**
     * Set the query where clause and retrieve the first matching document.
     *
     * @param callable|string $name
     * @param int|string|null $operator
     * @param mixed|null      $value
     *
     * @return T|null
     * @throws InvalidArgumentException
     */
    public function firstWhere(
        callable|string $name,
        int|string|null $operator = Builder::OPERATOR_EQUAL,
        mixed $value = null
    ): Model|null {
        return $this
            ->where($name, $operator, $value)
            ->first();
    }

    /**
     * Generate the unique cache key for the query.
     *
     * @return string
     */
    public function generateCacheKey(): string
    {
        try {
            return md5($this->toJson());
        } catch (JsonException) {
            return md5(serialize($this));
        }
    }

    /**
     * Increment a document field
     *
     * @param string $field
     * @param int    $count
     *
     * @return array{
     *   _shards: array{
     *      total: int,
     *      failed: int,
     *      successful: int
     *   },
     *   _index: string,
     *   _id: string,
     *   _version: int,
     *   _primary_term: int,
     *   _seq_no: int,
     *   result: string
     * }
     */
    public function increment(string $field, int $count = 1): array
    {
        return $this->script("ctx._source.{$field} += params.count", [
            'count' => $count,
        ]);
    }

    /**
     * Insert a document
     *
     * @param array       $attributes
     * @param string|null $id
     *
     * @return array{
     *     _shards: array{
     *         total: int,
     *         failed: int,
     *         successful: int
     *     },
     *     _index: string,
     *     _id: string,
     *     _version: int,
     *     _seq_no: int,
     *     _primary_term: int,
     *     result: string
     * }
     */
    public function insert(array $attributes, string|null $id = null): array
    {
        if ($id) {
            $this->id($id);
        }

        $parameters = [
            'body' => array_diff_key($attributes, array_flip([
                '_id',
                '_index',
            ])),
        ];

        if (count($ignores = $this->getIgnores())) {
            $parameters['client'] = [
                'ignore' => $ignores,
            ];
        }

        $parameters = $this->addBaseParams($parameters);

        if ($id = $this->getId()) {
            $parameters['id'] = $id;
        }

        return $this->getConnection()->insert($parameters);
    }

    /**
     * Paginate collection of results
     *
     * @param int      $perPage
     * @param string   $pageName
     * @param int|null $page
     *
     * @return Pagination<T>
     * @throws InvalidArgumentException
     */
    public function paginate(
        int $perPage = 10,
        string $pageName = 'page',
        int|null $page = null
    ): Pagination {
        $this->take($perPage);

        // Check if the request originated from outside a request context, eg.
        // the PHP command line SAPI
        if (PHP_SAPI === 'cli') {
            $page = $page ?: 1;

            $this->skip(($page * $perPage) - $perPage);

            $collection = $this->get();

            return new Pagination(
                $collection,
                $collection->getTotal() ?? 0,
                $perPage,
                $page
            );
        }

        $page = $page ?: (int)Request::query($pageName, '1');

        $this->skip(($page * $perPage) - $perPage);

        $collection = $this->get();

        return new Pagination(
            $collection,
            $collection->getTotal() ?? 0,
            $perPage,
            $page,
            [
                'path' => Request::url(),
                'query' => Request::query(),
            ]
        );
    }

    /**
     * Indicate that the query results should be cached.
     *
     * @param DateTime|int $ttl Cache TTL in seconds.
     * @param string|null  $key Cache key to use. Will be generated
     *                          automatically if omitted.
     *
     * @return $this
     */
    public function remember(DateTime|int $ttl, string|null $key = null): self
    {
        $this->cacheTtl = $ttl;
        $this->cacheKey = $key;

        return $this;
    }

    /**
     * Indicate that the query results should be cached forever.
     *
     * @param string|null $key
     *
     * @return $this
     */
    public function rememberForever(string|null $key = null): self
    {
        return $this->remember(-1, $key);
    }

    /**
     * Update by script
     *
     * @param mixed $script
     * @param array $params
     *
     * @return array{
     *   _shards: array{
     *      total: int,
     *      failed: int,
     *      successful: int
     *   },
     *   _index: string,
     *   _id: string,
     *   _version: int,
     *   _primary_term: int,
     *   _seq_no: int,
     *   result: string
     * }
     */
    public function script(mixed $script, array $params = []): array
    {
        $parameters = [
            'id' => $this->getId(),
            'body' => [
                'script' => [
                    'inline' => $script,
                    'params' => $params,
                ],
            ],
        ];

        if (count($ignores = $this->getIgnores())) {
            $parameters['client'] = [
                'ignore' => $ignores,
            ];
        }

        $parameters = $this->addBaseParams($parameters);

        return $this->getConnection()->getClient()->update(
            $parameters
        );
    }

    /**
     * Update a document
     *
     * @param array           $attributes
     * @param int|string|null $id
     *
     * @return array{
     *   _shards: array{
     *      total: int,
     *      failed: int,
     *      successful: int
     *   },
     *   _index: string,
     *   _id: string,
     *   _version: int,
     *   _primary_term: int,
     *   _seq_no: int,
     *   result: string
     * }
     */
    public function update(array $attributes, int|string $id = null): array
    {
        if ($id) {
            $this->id($id);
        }

        unset(
            $attributes['_highlight'],
            $attributes['_index'],
            $attributes['_score'],
            $attributes['_id'],
        );

        $parameters = [
            'id' => $this->getId(),
            'body' => [
                'doc' => $attributes,
            ],
        ];

        if (count($ignores = $this->getIgnores())) {
            $parameters['client'] = [
                'ignore' => $ignores,
            ];
        }

        $parameters = $this->addBaseParams($parameters);

        return $this
            ->getConnection()
            ->getClient()
            ->update($parameters);
    }

    /**
     * Processes a result and turns it into a model instance.
     *
     * @param array{
     *            _index: string,
     *            _id: string,
     *            _score: string,
     *            _source: T,
     *            fields: array<string, array>|null
     *        } $document Raw document to create a model instance from
     *
     * @return T Model instance representing the source document
     */
    protected function createModelInstance(array $document): Model
    {
        $data = $document['_source'] ?? [];
        $metadata = array_diff_key($document, array_flip([
            '_source',
        ]));

        return $this->getModel()->newInstance(
            $data,
            $metadata,
            true,
            $document['_index'] ?? null,
        );
    }

    /**
     * @return CacheInterface|null
     */
    protected function getCache(): CacheInterface|null
    {
        return $this->getConnection()->getCache();
    }

    /**
     * Executes the query and handles the result
     *
     * @param string|null $scrollId
     *
     * @return null|array{
     *     _scroll_id: string|null,
     *     took: int,
     *     timed_out: bool,
     *     _shards: array{
     *         total: int,
     *         successful: int,
     *         skipped: int,
     *         failed: int
     *     },
     *     hits: array{
     *         total: array{
     *             value: int,
     *             relation: string
     *         }|int,
     *         max_score: float,
     *         hits: array<int, array{
     *              _index: string,
     *              _id: string,
     *              _score: string,
     *              _source: T,
     *              fields: array<string, array>|null
     *         }>
     *     },
     *     suggest: null|array,
     *     aggregations: null|array<string, array{
     *         doc_count_error_upper_bound: int,
     *         sum_other_doc_count: int,
     *         buckets: array<int, array{
     *             key: string,
     *             doc_count: int
     *         }>,
     *         meta: array<string, mixed>|null
     *     }>,
     * }
     * @throws InvalidArgumentException
     */
    protected function getResult(string|null $scrollId = null): array|null
    {
        if ( ! $this->cacheTtl) {
            return $this->performSearch($scrollId);
        }

        if ($cache = $this->getCache()) {
            try {
                return $cache->get($this->getCacheKey());
            } catch (Throwable|InvalidArgumentException) {
                // If the cache didn't like our cache key (which should be
                // impossible), we regard it as a cache failure and perform a
                // normal search instead.
            }
        }

        return $this->performSearch($scrollId);
    }

    /**
     * Get non-cached results
     *
     * @param string|null $scrollId
     *
     * @return null|array{
     *     _scroll_id: string|null,
     *     took: int,
     *     timed_out: bool,
     *     _shards: array{
     *         total: int,
     *         successful: int,
     *         skipped: int,
     *         failed: int
     *     },
     *     hits: array{
     *         total: array{
     *             value: int,
     *             relation: string
     *         }|int,
     *         max_score: float,
     *         hits: array<int, array{
     *              _index: string,
     *              _id: string,
     *              _score: string,
     *              _source: T,
     *              fields: array<string, array>|null
     *         }>
     *     },
     *     suggest: null|array,
     *     aggregations: null|array<string, array{
     *         doc_count_error_upper_bound: int,
     *         sum_other_doc_count: int,
     *         buckets: array<int, array{
     *             key: string,
     *             doc_count: int
     *         }>,
     *         meta: array<string, mixed>|null
     *     }>,
     * }
     * @throws InvalidArgumentException
     */
    protected function performSearch(string|null $scrollId = null): array|null
    {
        $scrollId = $scrollId ?? $this->getScrollId();

        if ($scrollId) {
            $result = $this
                ->getConnection()
                ->getClient()
                ->scroll([
                    'scroll' => $this->getScroll(),
                    'scroll_id' => $scrollId,
                ])->asArray();
        } else {
            $query = $this->buildQuery();
            $result = $this
                ->getConnection()
                ->getClient()
                ->search($query)
                ->asArray();
        }

        // We attempt to cache the results if we have a cache instance, and the
        // TTl is truthy. This allows to use values such as `-1` to flush it.
        if ($this->cacheTtl && ($cache = $this->getCache())) {
            $cache->set(
                $this->getCacheKey(),
                $result,
                $this->cacheTtl instanceof DateTime
                    ? $this->cacheTtl->getTimestamp()
                    : $this->cacheTtl
            );
        }

        return $result;
    }

    /**
     * Retrieves all documents from a response.
     *
     * @param array{
     *     _scroll_id: string|null,
     *     took: int,
     *     timed_out: bool,
     *     _shards: array{
     *         total: int,
     *         successful: int,
     *         skipped: int,
     *         failed: int
     *     },
     *     hits: array{
     *         total: array{
     *             value: int,
     *             relation: string
     *         }|int,
     *         max_score: float,
     *         hits: array<int, array{
     *              _index: string,
     *              _id: string,
     *              _score: string,
     *              _source: T,
     *              fields: array<string, array>|null
     *         }>
     *     },
     *     suggest: null|array,
     *     aggregations: null|array<string, array{
     *         doc_count_error_upper_bound: int,
     *         sum_other_doc_count: int,
     *         buckets: array<int, array{
     *             key: string,
     *             doc_count: int
     *         }>,
     *         meta: array<string, mixed>|null
     *     }>,
     * } $response Response to extract documents from
     *
     * @return Collection<int, T> Collection of model instances representing the
     *                            documents contained in the response
     */
    protected function transformIntoCollection(array $response = []): Collection
    {
        /**
         * @var array<int, array{
         *     _index: string,
         *     _id: string,
         *     _score: string,
         *     _source: T,
         *     fields: array<string, array>|null
         * }> $results
         */
        $results = Arr::get($response, 'hits.hits', []);
        $documents = array_map(
            fn(array $document): Model => $this->createModelInstance(
                $document
            ),
            $results
        );

        return Collection::fromResponse(
            $response,
            $documents
        );
    }

    /**
     * Retrieve the first document from a response. If the response does not
     * contain any hits, will return `null`.
     *
     * @param array[] $response Response to extract the first document from
     *
     * @return T|null Model instance if any documents were found in the
     *                response, `null` otherwise
     */
    protected function transformIntoModel(array $response = []): Model|null
    {
        $source = Arr::get($response, 'hits.hits.0');

        if ( ! $source) {
            return null;
        }

        return $this->createModelInstance($source);
    }

    /**
     * Adds the base parameters required for all queries.
     *
     * @param array<string, mixed> $params Query parameters to hydrate
     *
     * @return array<string, mixed> Hydrated query parameters
     */
    private function addBaseParams(array $params): array
    {
        if ($index = $this->getIndex()) {
            $params['index'] = $index;
        }

        return $params;
    }
}
