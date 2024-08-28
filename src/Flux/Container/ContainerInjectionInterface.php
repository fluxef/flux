<?php
declare(strict_types=1);

namespace Flux\Container;

interface ContainerInjectionInterface
{
    public static function create(ContainerInterface $container): ContainerInjectionInterface;
}

