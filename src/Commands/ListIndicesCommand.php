<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;

use function app;
use function array_key_exists;
use function config;
use function count;
use function explode;
use function is_array;
use function trim;

/**
 * List Indices Command
 *
 * @bundle Matchory\Elasticsearch
 * @psalm-suppress PropertyNotSetInConstructor
 */
class ListIndicesCommand extends Command
{
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all indices';

    /**
     * Indices headers
     *
     * @var array
     */
    protected array $headers = [
        'configured (es.php)',
        'health',
        'status',
        'index',
        'uuid',
        'pri',
        'rep',
        'docs.count',
        'docs.deleted',
        'store.size',
        'pri.store.size',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'es:indices:list {--connection= : Elasticsearch connection}';

    private ConnectionResolverInterface $connectionResolver;

    public function __construct()
    {
        parent::__construct();

        /** @var ConnectionResolverInterface $resolver */
        $resolver = app(ConnectionResolverInterface::class);
        $this->connectionResolver = $resolver;
    }

    /**
     * Get a list of indices data
     * Match newer versions of elasticsearch/elasticsearch package (5.1.1 or higher)
     *
     * @param $indices
     *
     * @return array
     */
    public function getIndicesFromArrayResponse($indices): array
    {
        $data = [];

        foreach ($indices as $row) {
            $row = array_key_exists($row['index'], config('es.indices'))
                ? Arr::prepend($row, 'yes')
                : Arr::prepend($row, 'no');

            $data[] = $row;
        }

        return $data;
    }

    /**
     * Get list of indices data
     * Match older versions of elasticsearch/elasticsearch package.
     *
     * @param $indices
     *
     * @return array
     */
    public function getIndicesFromStringResponse($indices): array
    {
        $lines = explode("\n", trim($indices));
        $data = [];

        foreach ($lines as $line) {
            $line_array = explode(' ', trim($line));
            $row = [];

            foreach ($line_array as $item) {
                if (trim($item) !== '') {
                    $row[] = $item;
                }
            }

            $row = array_key_exists($row[2], config('es.indices'))
                ? Arr::prepend($row, 'yes')
                : Arr::prepend($row, 'no');

            $data[] = $row;
        }

        return $data;
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $connectionName = $this->option('connection') ?: config('es.default');
        $client = $this->connectionResolver
            ->connection($connectionName)
            ->getClient();

        $indices = $client->cat()->indices();
        $indices = is_array($indices)
            ? $this->getIndicesFromArrayResponse($indices)
            : $this->getIndicesFromStringResponse($indices);

        if (count($indices)) {
            $this->table($this->headers, $indices);
        } else {
            $this->warn('No indices found.');
        }
    }
}
