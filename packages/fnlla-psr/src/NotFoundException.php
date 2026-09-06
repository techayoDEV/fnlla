<?php

declare(strict_types=1);

namespace Fnlla\Psr;

final class NotFoundException extends ContainerException implements \Psr\Container\NotFoundExceptionInterface
{
}
