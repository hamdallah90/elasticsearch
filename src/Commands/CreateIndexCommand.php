<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Commands;

use Illuminate\Console\Command;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;

use function array_keys;
use function assert;
use function config;
use function is_null;
use function is_string;

/**
 * Create Index Command
 *
 * @bundle Matchory\Elasticsearch
 * @psalm-suppress PropertyNotSetInConstructor
 */
class CreateIndexCommand extends Command
{
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new index using defined setting and mapping in config file';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'es:indices:create {index?}{--connection= : Elasticsearch connection}';

    /**
     * Execute the console command.
     *
     * @param ConnectionResolverInterface $resolver
     */
    public function handle(ConnectionResolverInterface $resolver): void
    {
        $connectionName = $this->option('connection') ?: config('es.default');
        assert(is_string($connectionName));
        $client = $resolver
            ->connection($connectionName)
            ->getClient();

        /** @var string[] $indices */
        $indices = ! is_null($this->argument('index'))
            ? [$this->argument('index')]
            : array_keys(config('es.indices'));

        foreach ($indices as $index) {
            $config = config("es.indices.{$index}");

            if (is_null($config)) {
                $this->warn("Missing configuration for index: {$index}");

                continue;
            }

            if ($client->indices()->exists(['index' => $index])) {
                $this->warn("Index {$index} already exists!");

                continue;
            }

            $this->info("Creating index: {$index}");

            // Create index with settings from config file
            $client->indices()->create([
                'index' => $index,
                'body' => [
                    'settings' => $config['settings'],
                ],

            ]);

            if (isset($config['aliases'])) {
                foreach ($config['aliases'] as $alias) {
                    $this->info(
                        "Creating alias: {$alias} for index: {$index}"
                    );

                    $client->indices()->updateAliases([
                        'body' => [
                            'actions' => [
                                [
                                    'add' => [
                                        'index' => $index,
                                        'alias' => $alias,
                                    ],
                                ],
                            ],

                        ],
                    ]);
                }
            }

            if (isset($config['mappings'])) {
                foreach ($config['mappings'] as $type => $mapping) {
                    $this->info(
                        "Creating mapping for type: {$type} in index: {$index}"
                    );

                    // Create mapping for type from config file
                    $client->indices()->putMapping([
                        'index' => $index,
                        'type' => $type,
                        'body' => $mapping,
                    ]);
                }
            }
        }
    }
}
