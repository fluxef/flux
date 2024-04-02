<?php
declare(strict_types=1);

namespace Flux\Container;


/**
 * Interface ContainerInjectionInterface
 * @package Flux\Container
 */
interface ContainerInjectionInterface
{
    /**
     * @param ContainerInterface $container
     * @return mixed
     */
    public static function create(ContainerInterface $container): ContainerInjectionInterface;
}

