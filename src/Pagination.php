<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\HtmlString;

use const EXTR_OVERWRITE;

/**
 * Pagination
 *
 * @mixin Collection
 * @package Matchory\Elasticsearch
 */
class Pagination extends LengthAwarePaginator
{
    /**
     * Render the paginator using the given view.
     *
     * @param string|null $view
     * @param array       $data
     *
     * @return Htmlable
     */
    public function links($view = 'default', $data = []): Htmlable
    {
        extract($data, EXTR_OVERWRITE);

        $paginator = $this;
        $elements = $this->elements();
        $basePath = __DIR__ . '/../resources/pagination/';
        $html = match ($view) {
            'bootstrap-4' => require $basePath . 'bootstrap-4.php',
            'default' => require $basePath . 'default.php',
            'simple-bootstrap-4' => require $basePath . 'simple-bootstrap-4.php',
            'simple-default' => require $basePath . 'simple-default.php',
            default => '',
        };

        return new HtmlString($html);
    }
}
