<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;
use Matchory\Elasticsearch\Index;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use RuntimeException;

use function array_key_exists;
use function ceil;
use function config;
use function count;
use function json_encode;

/**
 * Reindex Command
 *
 * @bundle         Matchory\Elasticsearch
 * @psalm-suppress PropertyNotSetInConstructor
 */
class ReindexCommand extends Command
{
    /**
     * ES connection name
     *
     * @var string
     */
    protected string $connectionName;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reindex indices data';

    /**
     * Scroll time
     *
     * @var string
     */
    protected string $scroll;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'es:indices:reindex {index}{new_index}
                            {--bulk-size=1000 : Scroll size}
                            {--skip-errors : Skip reindexing errors}
                            {--hide-errors : Hide reindexing errors}
                            {--scroll=2m : query scroll time}
                            {--connection= : Elasticsearch connection}';

    /**
     * Query bulk size
     *
     * @var integer
     */
    protected int $size;

    private ConnectionResolverInterface $resolver;

    /**
     * Execute the console command.
     *
     * @param ConnectionResolverInterface $resolver
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws RuntimeException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function handle(ConnectionResolverInterface $resolver): void
    {
        $this->resolver = $resolver;
        $this->connectionName = $this->option('connection') ?: config('es.default');
        $connection = $resolver->connection($this->connectionName);
        $this->size = (int)$this->option('bulk-size');
        $this->scroll = $this->option('scroll');

        if ($this->size <= 0) {
            $this->warn('Invalid size value');

            return;
        }

        /** @var string $originalIndex */
        $originalIndex = $this->argument('index');
        /** @var string $newIndex */
        $newIndex = $this->argument('new_index');

        if ( ! array_key_exists($originalIndex, config('es.indices'))) {
            $this->warn("Missing configuration for index: {$originalIndex}");

            return;
        }

        if ( ! array_key_exists($newIndex, config('es.indices'))) {
            $this->warn("Missing configuration for index: {$newIndex}");

            return;
        }

        $old = new Index($connection, $originalIndex);
        $new = new Index($connection, $newIndex);

        $this->migrate($old, $new);
    }

    /**
     * Migrate data with Scroll queries & Bulk API
     *
     * @param Index       $originalIndex
     * @param Index       $newIndex
     * @param string|null $scrollId
     * @param int         $errors
     * @param int         $page
     *
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws RuntimeException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function migrate(
        Index $originalIndex,
        Index $newIndex,
        string|null $scrollId = null,
        int $errors = 0,
        int $page = 1
    ): void {
        $connection = $this->resolver->connection($this->connectionName);

        if ($page === 1) {
            $pages = (int)ceil($originalIndex->count() / $this->size);

            $this->output->progressStart($pages);

            $response = $connection
                ->index($originalIndex->getName())
                ->scroll($this->scroll)
                ->take($this->size)
                ->performSearch();
        } else {
            $response = $connection
                ->index($originalIndex->getName())
                ->scroll($this->scroll)
                ->scrollID($scrollId ?: '')
                ->performSearch();
        }

        $documents = $response['hits']['hits'] ?? [];

        if (count($documents) > 0) {
            $params = [];

            foreach ($documents as $document) {
                $params['body'][] = [
                    'index' => [
                        '_index' => $newIndex,
                        '_id' => $document['_id'],
                    ],
                ];

                $params['body'][] = $document['_source'];
            }

            $bulkResponse = $connection->getClient()->bulk($params);

            if (isset($bulkResponse['errors']) && $bulkResponse['errors']) {
                if ( ! $this->option('hide-errors')) {
                    $items = json_encode(
                        $bulkResponse['items'],
                        JSON_THROW_ON_ERROR
                    );

                    if ( ! $this->option('skip-errors')) {
                        $this->warn("\n{$items}");

                        return;
                    }

                    $this->warn("\n{$items}");
                }

                $errors++;
            }

            $this->output->progressAdvance();
        } else {
            // Reindexing finished
            $this->output->progressFinish();

            $total = $connection
                ->index($originalIndex->getName())
                ->count();

            if ($errors > 0) {
                $this->warn(
                    "{$total} documents re-indexed with {$errors} errors."
                );

                return;
            }

            $this->info("{$total} documents re-indexed successfully.");

            return;
        }

        $page++;

        $this->migrate(
            $originalIndex,
            $newIndex,
            $response['_scroll_id'],
            $errors,
            $page
        );
    }

}
