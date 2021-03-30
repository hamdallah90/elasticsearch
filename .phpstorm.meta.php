<?php

declare(strict_types=1);

namespace PHPSTORM_META
{
    expectedArguments(
\Matchory\Elasticsearch\Builder::regexpFilter(),
        2,
        [
            \Matchory\Elasticsearch\Builder::REGEXP_FLAG_ALL,
            \Matchory\Elasticsearch\Builder::REGEXP_FLAG_NONE,
            \Matchory\Elasticsearch\Builder::REGEXP_FLAG_ANYSTRING,
            \Matchory\Elasticsearch\Builder::REGEXP_FLAG_COMPLEMENT,
            \Matchory\Elasticsearch\Builder::REGEXP_FLAG_INTERVAL,
            \Matchory\Elasticsearch\Builder::REGEXP_FLAG_INTERSECTION,
        ]
    );
}
