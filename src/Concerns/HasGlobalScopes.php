<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Concerns;

use Closure;
use InvalidArgumentException;
use Matchory\Elasticsearch\Interfaces\ScopeInterface;

use function get_class;
use function is_null;
use function is_string;
use function spl_object_hash;

/**
 * Has Global Scopes Trait
 * =======================
 * Adds the ability to use global Elasticsearch query scopes. This concern trait
 * is built similar to the Eloquent trait, but makes use of the Elasticsearch
 * query builder.
 *
 * @package Matchory\Elasticsearch\Concerns
 * @see     \Illuminate\Database\Eloquent\Concerns\HasGlobalScopes
 */
trait HasGlobalScopes
{
    /**
     * @var array<class-string, array<string, Closure|ScopeInterface>>
     */
    protected static array $globalScopes = [];

    /**
     * Get a global scope registered with the model.
     *
     * @param string|ScopeInterface $scope
     *
     * @return ScopeInterface|Closure|null
     */
    public static function getGlobalScope(
        ScopeInterface|string $scope
    ): ScopeInterface|Closure|null {
        if (is_string($scope)) {
            return static::$globalScopes[static::class][$scope] ?? null;
        }

        return static::$globalScopes[static::class][get_class($scope)] ?? null;
    }

    /**
     * Get the global scopes for this class instance.
     *
     * @return array<string, ScopeInterface>
     * @psalm-return array<string, Closure|ScopeInterface>
     */
    public function getGlobalScopes(): array
    {
        return static::$globalScopes[static::class] ?? [];
    }

    /**
     * Register a new global scope on the model.
     *
     * @param string|Closure|ScopeInterface $scope
     * @param Closure|null                  $implementation
     *
     * @return Closure|ScopeInterface
     *
     * @throws InvalidArgumentException
     */
    public static function addGlobalScope(
        ScopeInterface|string|Closure $scope,
        Closure|null $implementation = null
    ): ScopeInterface|Closure {
        return match (true) {
            (is_string($scope) && ! is_null($implementation)) => (
            static::$globalScopes[static::class][$scope] = $implementation
            ),

            $scope instanceof Closure => (
            static::$globalScopes[static::class][spl_object_hash($scope)] = $scope
            ),

            $scope instanceof ScopeInterface => (
            static::$globalScopes[static::class][get_class($scope)] = $scope
            ),

            default => throw new InvalidArgumentException(
                'Global scopes must be callable or implement ScopeInterface'
            )
        };
    }

    /**
     * Determine if a model has a global scope.
     *
     * @param string|ScopeInterface $scope
     *
     * @return bool
     */
    public static function hasGlobalScope(ScopeInterface|string $scope): bool
    {
        return (bool)static::getGlobalScope($scope);
    }
}
