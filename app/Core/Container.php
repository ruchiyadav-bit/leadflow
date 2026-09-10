<?php
declare(strict_types=1);

namespace LeadFlow\Core;

final class Container
{
    /** @var array<string, callable> */
    private array $bindings = [];
    /** @var array<string, object> */
    private array $instances = [];

    public function singleton(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
        unset($this->instances[$abstract]);
    }

    public function get(string $abstract): object
    {
        if (isset($this->instances[$abstract])) return $this->instances[$abstract];
        if (isset($this->bindings[$abstract])) {
            return $this->instances[$abstract] = ($this->bindings[$abstract])($this);
        }
        if (!class_exists($abstract)) {
            throw new \RuntimeException("Cannot resolve: {$abstract}");
        }
        return $this->instances[$abstract] = $this->build($abstract);
    }

    private function build(string $class): object
    {
        $ref = new \ReflectionClass($class);
        if (!$ref->isInstantiable()) {
            throw new \RuntimeException("Not instantiable: {$class}");
        }
        $ctor = $ref->getConstructor();
        if ($ctor === null || $ctor->getNumberOfParameters() === 0) {
            return new $class();
        }
        $args = [];
        foreach ($ctor->getParameters() as $p) {
            $type = $p->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->get($type->getName());
            } elseif ($p->isDefaultValueAvailable()) {
                $args[] = $p->getDefaultValue();
            } elseif ($p->allowsNull()) {
                $args[] = null;
            } else {
                throw new \RuntimeException("Cannot resolve parameter {$p->getName()} of {$class}");
            }
        }
        return $ref->newInstanceArgs($args);
    }
}
