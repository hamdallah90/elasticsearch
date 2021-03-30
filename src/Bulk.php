<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch;

/**
 * Class Bulk
 *
 * @package Matchory\Elasticsearch\Classes
 */
class Bulk
{
    /**
     * Operation count which will trigger autocommit
     *
     * @var int
     */
    public int $autocommitAfter = 0;

    /**
     * Bulk body
     *
     * @var array
     */
    public array $body = [];

    /**
     * The query object
     *
     * @var Builder
     */
    public Builder $builder;

    /**
     * The document key
     *
     * @var string|null
     */
    public string|null $id = null;

    /**
     * The index name
     *
     * @var string|null
     */
    public string|null $index = null;

    /**
     * Number of pending operations
     *
     * @var int
     */
    public int $operationCount = 0;

    /**
     * @param Builder  $builder
     * @param int|null $autocommitAfter
     */
    public function __construct(
        Builder $builder,
        int|null $autocommitAfter = null
    ) {
        $this->builder = $builder;

        $this->autocommitAfter = (int)$autocommitAfter;
    }

    /**
     * Add pending document abstract action
     *
     * @param string $actionType
     * @param array  $data
     *
     * @return bool
     */
    public function action(string $actionType, array $data = []): bool
    {
        $this->body['body'][] = [
            $actionType => [
                '_index' => $this->getIndex(),
                '_id' => $this->id,
            ],
        ];

        if ( ! empty($data)) {
            $this->body['body'][] = $actionType === 'update'
                ? ['doc' => $data]
                : $data;
        }

        $this->operationCount++;

        $this->reset();

        if (
            $this->autocommitAfter > 0 &&
            $this->operationCount >= $this->autocommitAfter
        ) {
            return (bool)$this->commit();
        }

        return true;
    }

    /**
     * Get Bulk body
     *
     * @return array
     */
    public function body(): array
    {
        return $this->body;
    }

    /**
     * Commit all pending operations
     *
     * @return array|null
     */
    public function commit(): array|null
    {
        if (empty($this->body)) {
            return null;
        }

        $result = $this
            ->builder
            ->getConnection()
            ->getClient()
            ->bulk($this->body);

        $this->operationCount = 0;
        $this->body = [];

        return $result;
    }

    /**
     * Add pending document for deletion
     *
     * @return bool
     */
    public function delete(): bool
    {
        return $this->action('delete');
    }

    /**
     * Filter by _id
     *
     * @param string|null $id
     *
     * @return $this
     */
    public function id(string|null $id = null): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set the index name
     *
     * @param string|null $index
     *
     * @return $this
     */
    public function index(string|null $index = null): self
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Add pending document for insert
     *
     * @param array $data
     *
     * @return bool
     */
    public function insert(array $data = []): bool
    {
        return $this->action('index', $data);
    }

    /**
     * Reset names
     *
     * @return void
     */
    public function reset(): void
    {
        $this->id();
        $this->index();
    }

    /**
     * Add pending document for update
     *
     * @param array $data
     *
     * @return bool
     */
    public function update(array $data = []): bool
    {
        return $this->action('update', $data);
    }

    /**
     * Get the index name
     *
     * @return string|null
     */
    protected function getIndex(): string|null
    {
        return $this->index ?: $this->builder->getIndex();
    }
}
