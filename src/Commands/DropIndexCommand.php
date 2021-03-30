<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use RuntimeException;

use function app;
use function array_keys;
use function config;
use function is_null;

/**
 * Drop Index Command
 *
 * @bundle Matchory\Elasticsearch
 * @psalm-suppress PropertyNotSetInConstructor
 */
class DropIndexCommand extends Command
{
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop an index';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'es:indices:drop {index?}
                            {--connection= : Elasticsearch connection}
                            {--force : Drop indices without any confirmation messages}';

    private ConnectionResolverInterface $connectionResolver;

    public function __construct()
    {
        parent::__construct();

        /** @var ConnectionResolverInterface $resolver */
        $resolver = app(ConnectionResolverInterface::class);
        $this->connectionResolver = $resolver;
    }

    /**
     * Execute the console command.
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function handle(): void
    {
        $connectionName = $this->option("connection") ?: config('es.default');
        $force = (int)($this->option('force') ?: 0);
        $client = $this->connectionResolver
            ->connection($connectionName)
            ->getClient();

        $indices = ! is_null($this->argument('index'))
            ? [$this->argument('index')]
            : array_keys(config('es.indices'));

        foreach ($indices as $index) {
            if ( ! $client->indices()->exists(['index' => $index])) {
                $this->warn("Index '{$index}' does not exist.");

                continue;
            }

            if (
                $force ||
                $this->confirm("Are you sure to drop '{$index}' index")
            ) {
                $this->info("Dropping index: {$index}");

                $client->indices()->delete(['index' => $index]);
            }
        }
    }
}
