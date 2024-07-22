<?php
declare(strict_types=1);

namespace Flux\Lock;


/**
 * Class LockFactory
 * @package Flux\Lock
 */
class LockFactory
{

    public function __construct()
    {
        // optional Parameter LockStorage
    }

    /**
     * @return LockFactory
     */
    public static function create(): LockFactory
    {
        // optionaler Parameter LockStorage
        return new static ();
    }

    /**
     * @param string $name
     * @param int $expire
     * @return LockInterface
     */
    public function createLock(string $name, int $expire = 0): LockInterface
    {
        return new Lock($name, $expire);
    }


}
