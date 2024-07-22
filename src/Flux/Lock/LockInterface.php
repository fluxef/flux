<?php
declare(strict_types=1);

namespace Flux\Lock;

/**
 * Interface LockInterface
 * @package Flux\Lock
 */
interface LockInterface
{
    public function __construct(string $lockname = '', int $expire = 0);

    public function acquire(): bool;

    public function release();

}
