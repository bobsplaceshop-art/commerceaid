<?php

declare(strict_types=1);

namespace CorporateAid\Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Minimal dependency injection container.
 *
 * Stores services so they are built once and reused.
 *
 * Resolution order in get():
 *
 *   1. If the id was registered with instance(), return the value.
 *   2. If the id has an explicit binding, run the factory.
 *   3. If the id names an instantiable class, auto-construct it
 *      via build().
 *   4. Otherwise, throw a clear error.
 *
 * Auto-construction is the fallback for the common case where a
 * class has a constructor with type-hinted dependencies and no
 * custom configuration. Explicit bindings always win. If a class
 * needs a captured closure, a config value, a non-default session,
 * or a method call after construction, it registers an explicit
 * binding.
 *
 * The reflection recipe (ReflectionClass plus a normalized
 * parameter list) is cached per class. Reflection runs once per
 * class per request, not once per resolve.
 *
 * has() stays strict. It reports whether an id has been explicitly
 * registered with bind(), singleton(), or instance(). It does not
 * report whether get() could auto-construct the id. The Kernel's
 * middleware resolver depends on this reading: it uses has() to
 * verify a middleware class was registered by its owning provider,
 * and its error message tells the developer to add the binding.
 */
final class Container
{
    /** @var array<string, Closure> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, bool> */
    private array $singletons = [];

    /**
     * Cached per-class construction recipe.
     *
     * Shape: class name => [
     *     'reflector' => ReflectionClass,
     *     'params'    => list<array{name:string, class:string|null, hasDefault:bool}>,
     * ]
     *
     * 'class' is the FQCN from a named non-builtin type hint, or
     * null when the parameter is untyped or has a union/intersection
     * type. Union and intersection types do not match
     * ReflectionNamedType, so they normalize to null; if such a
     * parameter also lacks a default, build() throws.
     *
     * @var array<string, array{reflector: ReflectionClass<object>, params: list<array{name:string, class:string|null, hasDefault:bool}>}>
     */
    private array $recipes = [];

    /**
     * Register a factory. If $singleton is true, the factory runs
     * once and the result is cached.
     */
    public function bind(string $id, Closure $factory, bool $singleton = false): void
    {
        $this->bindings[$id] = $factory;
        if ($singleton) {
            $this->singletons[$id] = true;
        }
    }

    public function singleton(string $id, Closure $factory): void
    {
        $this->bind($id, $factory, true);
    }

    /**
     * Register an already-built value.
     */
    public function instance(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
    }

    /**
     * True when the id has been explicitly registered with
     * bind(), singleton(), or instance(). Does not reflect on
     * class names. See the class docblock for why this is
     * intentionally strict.
     */
    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->bindings[$id]);
    }

    /**
     * Resolve a service.
     *
     * Order of resolution: cached instance, explicit binding,
     * auto-construction of a concrete class, throw. See the class
     * docblock for the full rule.
     */
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->bindings[$id])) {
            $value = ($this->bindings[$id])($this);

            if (isset($this->singletons[$id])) {
                $this->instances[$id] = $value;
            }

            return $value;
        }

        if (class_exists($id)) {
            return $this->build($id);
        }

        throw new RuntimeException(
            "Container: nothing bound for '{$id}', and '{$id}' is not an instantiable class."
        );
    }

    /**
     * Auto-construct a concrete class by resolving its constructor
     * parameters recursively through get().
     *
     * The built instance is cached as a singleton. Subsequent calls
     * to get() or build() for the same class return the cached
     * instance. This matches the codebase convention that every
     * container-resolved service is a singleton within a request.
     *
     * Guards:
     *   - The class must be instantiable (not abstract, not an
     *     interface, not a trait).
     *   - Every type-hinted constructor parameter must name a class
     *     or interface that get() can resolve.
     *   - Every untyped parameter must have a default value.
     *
     * On any guard failure, a RuntimeException names the class and
     * the offending parameter.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function build(string $class): object
    {
        if (isset($this->instances[$class])) {
            /** @var T $cached */
            $cached = $this->instances[$class];
            return $cached;
        }

        $recipe      = $this->recipe($class);
        $reflector   = $recipe['reflector'];

        if (!$reflector->isInstantiable()) {
            throw new RuntimeException(
                "Container: '{$class}' is not instantiable (abstract class, interface, or trait)."
            );
        }

        $args = [];

        foreach ($recipe['params'] as $param) {
            $name       = $param['name'];
            $paramClass = $param['class'];
            $hasDefault = $param['hasDefault'];

            if ($paramClass !== null) {
                $args[] = $this->get($paramClass);
                continue;
            }

            if ($hasDefault) {
                // Stop passing positional arguments here. PHP fills
                // the remaining parameters from their declared
                // defaults. This is safe because PHP requires that
                // no required parameter follows an optional one, so
                // every parameter after this point is also optional.
                break;
            }

            throw new RuntimeException(
                "Container: cannot auto-construct '{$class}'. " .
                "Parameter \${$name} has no type hint and no default value. " .
                "Add an explicit binding for '{$class}' in its owning provider."
            );
        }

        $instance = $reflector->newInstanceArgs($args);

        $this->instances[$class] = $instance;

        /** @var T $instance */
        return $instance;
    }

    /**
     * Build or fetch the cached construction recipe for a class.
     *
     * @param class-string $class
     * @return array{reflector: ReflectionClass<object>, params: list<array{name:string, class:string|null, hasDefault:bool}>}
     */
    private function recipe(string $class): array
    {
        if (isset($this->recipes[$class])) {
            return $this->recipes[$class];
        }

        $reflector   = new ReflectionClass($class);
        $constructor = $reflector->getConstructor();

        $params = [];

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $param) {
                $type      = $param->getType();
                $className = null;

                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $className = $type->getName();
                }

                $params[] = [
                    'name'       => $param->getName(),
                    'class'      => $className,
                    'hasDefault' => $param->isDefaultValueAvailable(),
                ];
            }
        }

        $recipe = [
            'reflector' => $reflector,
            'params'    => $params,
        ];

        $this->recipes[$class] = $recipe;

        return $recipe;
    }
}
