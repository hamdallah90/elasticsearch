<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch;

use Elasticsearch\Client;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\InvalidCastException;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Psr\SimpleCache\InvalidArgumentException;

use function array_filter;
use function array_merge;
use function collect;
use function count;
use function is_array;

class ScoutEngine extends Engine
{
    /**
     * ScoutEngine constructor.
     *
     * @param Client $client Elasticsearch client
     * @param string $index  Index where the models will be saved
     */
    public function __construct(
        private readonly Client $client,
        private readonly string $index
    ) {
    }

    public function createIndex($name, array $options = [])
    {
        // TODO: Implement createIndex() method.
    }

    /**
     * Remove the given model from the index.
     *
     * @param Collection $models
     *
     * @return void
     * @throws InvalidCastException
     */
    public function delete($models): void
    {
        $params = [
            'body' => [],
        ];

        $models->each(function (Model $model) use (&$params) {
            $params['body'][] = [
                'delete' => [
                    '_id' => $model->getKey(),
                    '_index' => $this->index,
                ],
            ];
        });

        $this->client->bulk($params);
    }

    public function deleteIndex($name)
    {
        // TODO: Implement deleteIndex() method.
    }

    /**
     * Flush all the model's records from the engine.
     *
     * @param Model $model
     *
     * @return void
     */
    public function flush($model): void
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $this->client->deleteByQuery([
            'type' => $model->searchableAs(),
            'index' => $this->index,
            'body' => [
                'query' => [
                    'match_all' => [],
                ],
            ],
        ]);
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param mixed $results
     *
     * @return int
     */
    public function getTotalCount($results): int
    {
        return (int)(is_array($results['hits']['total'])
            ? $results['hits']['total']['value']
            : $results['hits']['total']);
    }

    public function lazyMap(Builder $builder, $results, $model)
    {
        // TODO: Implement lazyMap() method.
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param Builder $builder
     * @param mixed   $results
     * @param Model   $model
     *
     * @return Collection
     * @throws InvalidArgumentException
     */
    public function map(Builder $builder, $results, $model): Collection
    {
        if ((int)$results['hits']['total'] === 0) {
            return Collection::make();
        }

        $keys = collect($results['hits']['hits'])
            ->pluck(Model::FIELD_ID)
            ->values()
            ->all();

        $models = $model
            ->newQuery()
            ->whereIn(Model::FIELD_ID, $keys)
            ->get()
            ->keyBy(Model::FIELD_ID);

        return Collection
            ::make($results['hits']['hits'])
            ->map(static fn(array $hit) => $models[$hit[Model::FIELD_ID]]);
    }

    /**
     * @param mixed $results
     *
     * @return Collection
     */
    public function mapIds($results): Collection
    {
        return new Collection([]);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @param int     $perPage
     * @param int     $page
     *
     * @return array|callable
     */
    public function paginate(Builder $builder, $perPage, $page): array|callable
    {
        $result = $this->performSearch($builder, [
            'numericFilters' => $this->filters($builder),
            'from' => (($page * $perPage) - $perPage),
            'size' => $perPage,
        ]);

        $result['nbPages'] = $result['hits']['total'] / $perPage;

        return $result;
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     *
     * @return array|callable
     */
    public function search(Builder $builder): array|callable
    {
        return $this->performSearch($builder, array_filter([
            'numericFilters' => $this->filters($builder),
            'size' => $builder->limit,
        ]));
    }

    /**
     * Update the given model in the index.
     *
     * @param Collection $models
     *
     * @return void
     * @throws InvalidCastException
     */
    public function update($models): void
    {
        $params = [
            'body' => [],
        ];

        $models->each(function (Model $model) use (&$params) {
            $params['body'][] = [
                'update' => [
                    Model::FIELD_ID => $model->getKey(),
                    '_index' => $this->index,
                ],
            ];

            $params['body'][] = [
                'doc' => $model->toSearchableArray(),
                'doc_as_upsert' => true,
            ];
        });

        $this->client->bulk($params);
    }

    /**
     * Get the filter array for the query.
     *
     * @param Builder $builder
     *
     * @return array
     */
    protected function filters(Builder $builder): array
    {
        return Collection
            ::make($builder->wheres)
            ->map(static fn(mixed $value, int|string $key): array => [
                'match_phrase' => [$key => $value],
            ])
            ->values()
            ->all();
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @param array   $options
     *
     * @return array|callable
     */
    protected function performSearch(
        Builder $builder,
        array $options = []
    ): array|callable {
        /** @noinspection PhpUndefinedMethodInspection */
        $params = [
            'index' => $this->index,
            'type' => $builder->model->searchableAs(),
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'query_string' => [
                                    'query' => $builder->query,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if (isset($options['from'])) {
            $params['body']['from'] = $options['from'];
        }

        if (isset($options['size'])) {
            $params['body']['size'] = $options['size'];
        }

        if (
            isset($options['numericFilters']) &&
            count($options['numericFilters'])
        ) {
            $params['body']['query']['bool']['must'] = array_merge(
                $params['body']['query']['bool']['must'],
                $options['numericFilters']
            );
        }

        return $this->client->search($params);
    }
}
