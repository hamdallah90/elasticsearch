<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection as BaseCollection;
use JsonException;

use function array_map;
use function is_array;
use function json_encode;

/**
 * Collection
 *
 * @package  Matchory\Elasticsearch
 * @template T of Model
 * @template-extends BaseCollection<array-key, T>
 */
class Collection extends BaseCollection
{
    /**
     * @param T[]         $items
     * @param int|null    $total
     * @param float|null  $maxScore
     * @param float|null  $duration
     * @param bool|null   $timedOut
     * @param string|null $scrollId
     * @param array|null  $shards
     * @param array|null  $suggestions
     * @param array|null  $aggregations
     */
    public function __construct(
        array $items = [],
        protected int|null $total = null,
        protected float|null $maxScore = null,
        protected float|null $duration = null,
        protected bool|null $timedOut = null,
        protected string|null $scrollId = null,
        protected array|null $shards = null,
        protected array|null $suggestions = null,
        protected array|null $aggregations = null
    ) {
        parent::__construct($items);
    }

    /**
     * @return BaseCollection<int, string>
     */
    public function getAggregations(): BaseCollection
    {
        return new BaseCollection($this->aggregations);
    }

    public function getAllSuggestions(): BaseCollection
    {
        return BaseCollection
            ::make($this->suggestions)
            ->mapInto(BaseCollection::class);
    }

    public function getDuration(): float|null
    {
        return $this->duration;
    }

    public function getMaxScore(): float|null
    {
        return $this->maxScore;
    }

    public function getScrollId(): string|null
    {
        return $this->scrollId;
    }

    public function getShards(): array|null
    {
        return $this->shards;
    }

    public function getSuggestions(string $name): BaseCollection
    {
        return new BaseCollection($this->suggestions[$name] ?? []);
    }

    public function getTotal(): int|null
    {
        return $this->total;
    }

    /**
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
     * }                 $response
     * @param array|null $items
     *
     * @return static
     */
    public static function fromResponse(
        array $response,
        array|null $items = null
    ): self {
        $hits = $response['hits'] ?? [];
        $items = $items ?? $hits['hits'] ?? [];
        $maxScore = (float)($hits['max_score'] ?? 0);
        $duration = (float)($response['took'] ?? 0);
        $timedOut = (bool)($response['timed_out'] ?? false);
        $scrollId = (string)($response['_scroll_id'] ?? null);
        $shards = $response['_shards'] ?? [];
        $suggestions = $response['suggest'] ?? [];
        $aggregations = $response['aggregations'] ?? [];
        $total = (int)(is_array($hits['total'])
            ? $hits['total']['value']
            : $hits['total']
        );

        return new self(
            $items,
            $total,
            $maxScore,
            $duration,
            $timedOut,
            $scrollId,
            $shards,
            $suggestions,
            $aggregations,
        );
    }

    public function isTimedOut(): bool|null
    {
        return $this->timedOut;
    }

    /**
     * @inheritdoc
     */
    public function toArray(): array
    {
        return array_map(static function ($item) {
            return $item instanceof Arrayable
                ? $item->toArray()
                : $item;
        }, $this->items);
    }

    /**
     * Get the collection of items as JSON.
     *
     * @param int $options
     *
     * @return string
     * @throws JsonException
     */
    public function toJson($options = 0): string
    {
        return json_encode(
            $this->toArray(),
            JSON_THROW_ON_ERROR | $options
        );
    }
}
