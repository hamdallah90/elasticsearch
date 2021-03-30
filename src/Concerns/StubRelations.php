<?php

/**
 * This file is part of elasticsearch, a Matchory application.
 *
 * Unauthorized copying of this file, via any medium, is strictly prohibited.
 * Its contents are strictly confidential and proprietary.
 *
 * @copyright 2020–2022 Matchory GmbH · All rights reserved
 * @author    Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\Elasticsearch\Concerns;

trait StubRelations
{
    /**
     * @param string $key
     *
     * @return mixed
     * @noinspection PhpMissingParamTypeInspection
     */
    public function getRelationValue($key): mixed
    {
        return null;
    }

    /**
     * @param string $key
     *
     * @return bool
     * @noinspection PhpMissingParamTypeInspection
     */
    public function isRelation($key): bool
    {
        return false;
    }

    public function relationsToArray(): array
    {
        return [];
    }
}
