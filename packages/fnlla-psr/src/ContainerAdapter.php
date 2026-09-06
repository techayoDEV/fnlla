<?php

declare(strict_types=1);

namespace Fnlla\Psr;

use Fnlla\Php\Container\Container;
use Psr\Container\ContainerInterface;

final class ContainerAdapter implements ContainerInterface
{
    public function __construct(private Container $container) {}

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new NotFoundException("Container entry not found: " . $id);
        }
        try {
            return $this->container->make($id);
        } catch (\Throwable $error) {
            throw new ContainerException("Unable to resolve container entry: " . $id, 0, $error);
        }
    }
}
