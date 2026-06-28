<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONTAINER SOURCE
File: src\Container\Container.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Implements dependency resolution for the maintained framework runtime.
*/

namespace Fnlla\Php\Container;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

final class Container
{
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $abstract, Closure|string|null $concrete = null, bool $shared = false): void
    {
        $this->bindings[$abstract] = [
            "concrete" => $concrete ?? $abstract,
            "shared" => $shared,
        ];
    }

    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function has(string $abstract): bool
    {
        return array_key_exists($abstract, $this->instances)
            || array_key_exists($abstract, $this->bindings)
            || class_exists($abstract);
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        if (array_key_exists($abstract, $this->instances)) {
            return $this->instances[$abstract];
        }

        $binding = $this->bindings[$abstract] ?? null;
        $concrete = $binding["concrete"] ?? $abstract;
        $object = $concrete instanceof Closure
            ? $concrete($this, $parameters)
            : $this->build($concrete, $parameters);

        if (($binding["shared"] ?? false) === true) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    public function call(callable|array $callable, array $parameters = []): mixed
    {
        if (is_array($callable)) {
            [$target, $method] = $callable;
            $instance = is_string($target) ? $this->make($target) : $target;
            $reflection = new ReflectionMethod($instance, $method);

            return $reflection->invokeArgs($instance, $this->resolveParameters($reflection->getParameters(), $parameters));
        }

        $reflection = new ReflectionFunction(Closure::fromCallable($callable));

        return $callable(...$this->resolveParameters($reflection->getParameters(), $parameters));
    }

    private function build(string $concrete, array $parameters = []): mixed
    {
        if (!class_exists($concrete)) {
            throw new RuntimeException("Unable to resolve container entry: " . $concrete);
        }

        $reflection = new ReflectionClass($concrete);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Class is not instantiable: " . $concrete);
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        return $reflection->newInstanceArgs($this->resolveParameters($constructor->getParameters(), $parameters));
    }

    private function resolveParameters(array $reflectionParameters, array $provided): array
    {
        $resolved = [];

        foreach ($reflectionParameters as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $provided)) {
                $resolved[] = $provided[$name];
                continue;
            }

            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $resolved[] = $this->make($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $resolved[] = $parameter->getDefaultValue();
                continue;
            }

            throw new RuntimeException("Unable to resolve parameter [{$name}] from container.");
        }

        return $resolved;
    }
}
