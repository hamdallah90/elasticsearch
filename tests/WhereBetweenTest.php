<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests;

use Matchory\Elasticsearch\Tests\Traits\ESQueryTrait;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\InvalidArgumentException;
use PHPUnit\Framework\MockObject\ClassAlreadyExistsException;
use PHPUnit\Framework\MockObject\ClassIsFinalException;
use PHPUnit\Framework\MockObject\DuplicateMethodException;
use PHPUnit\Framework\MockObject\InvalidMethodNameException;
use PHPUnit\Framework\MockObject\OriginalConstructorInvocationRequiredException;
use PHPUnit\Framework\MockObject\ReflectionException;
use PHPUnit\Framework\MockObject\RuntimeException;
use PHPUnit\Framework\MockObject\UnknownTypeException;
use PHPUnit\Framework\TestCase;

class WhereBetweenTest extends TestCase
{
    use ESQueryTrait;

    /**
     * @throws ClassAlreadyExistsException
     * @throws ClassIsFinalException
     * @throws DuplicateMethodException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @test
     */
    public function whereBetween(): void
    {
        self::assertEquals(
            $this->getExpected('views', 500, 1000),
            $this->getActual('views', 500, 1000)
        );

        self::assertEquals(
            $this->getExpected('views', [500, 1000]),
            $this->getActual('views', [500, 1000])
        );
    }

    /**
     * @param      $name
     * @param      $first
     * @param null $last
     *
     * @return array
     * @throws InvalidArgumentException
     * @throws ClassAlreadyExistsException
     * @throws ClassIsFinalException
     * @throws DuplicateMethodException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     */
    protected function getActual($name, $first, $last = null): array
    {
        return $this
            ->getQueryObject()
            ->whereBetween($name, $first, $last)
            ->toArray();
    }

    /**
     * @param $name
     * @param $first
     * @param $last
     *
     * @return array
     */
    protected function getExpected($name, $first, $last = null): array
    {
        $query = $this->getQueryArray();

        if (is_array($first) && count($first) === 2) {
            [$first, $last] = $first;
        }

        $query['body']['query']['bool']['filter'][] = [
            'range' => [
                $name => [
                    'gte' => $first,
                    'lte' => $last,
                ],
            ],
        ];

        return $query;
    }
}
