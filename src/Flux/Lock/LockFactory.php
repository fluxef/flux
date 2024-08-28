<?php
declare(strict_types=1);

namespace Flux\Lock;

class LockFactory
{

    public function __construct()
    {
        // optional Parameter LockStorage
    }

    public static function create(): LockFactory
    {
        // optional Parameter LockStorage
        return new static ();
    }

    public function createLock(string $name, int $expire = 0): LockInterface
    {
        return new Lock($name, $expire);
    }


}
