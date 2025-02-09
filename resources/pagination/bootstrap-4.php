<?php

declare(strict_types=1);

/**
 * @var Pagination $paginator
 * @var string[][] $elements
 */

use Matchory\Elasticsearch\Pagination;

?>
<?php if ($paginator->hasPages()): ?>
    <ul class="pagination">
        <?php if ($paginator->onFirstPage()): ?>
            <li class="page-item disabled">
                <span class="page-link">&laquo;</span>
            </li>
        <?php else: ?>
            <li class="page-item">
                <a class="page-link"
                   href="<?= $paginator->previousPageUrl() ?>"
                   rel="prev">&laquo;</a>
            </li>
        <?php endif ?>

        <?php foreach ($elements as $element): ?>
            <?php if (is_string($element)): ?>
                <li class="page-item disabled">
                    <span class="page-link"><?= $element ?></span>
                </li>
            <?php endif ?>

            <?php if (is_array($element)): ?>
                <?php foreach ($element as $page => $url): ?>
                    <?php if ($page === $paginator->currentPage()): ?>
                        <li class="page-item active">
                            <span class="page-link"><?= $page ?></span>
                        </li>
                    <?php else: ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $url ?>">
                                <?= $page ?>
                            </a>
                        </li>
                    <?php endif ?>
                <?php endforeach ?>
            <?php endif ?>
        <?php endforeach ?>


        <?php if ($paginator->hasMorePages()): ?>
            <li class="page-item">
                <a class="page-link"
                   href="<?= $paginator->nextPageUrl() ?>"
                   rel="next">&raquo;</a>
            </li>
        <?php else: ?>
            <li class="page-item disabled">
                <span class="page-link">&raquo;</span>
            </li>
        <?php endif ?>
    </ul>
<?php endif ?>
