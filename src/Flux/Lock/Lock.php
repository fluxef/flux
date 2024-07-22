<?php
declare(strict_types=1);

namespace Flux\Lock;

/**
 * Class Lock
 * @package Flux\Lock
 */
class Lock implements LockInterface
{

    protected mixed $lockfilepointer = null;    // ?resource
    protected string $lockfilename = '';
    protected bool $locked = false;

    public function __construct(protected string $lockname = '', protected int $expire = 0)
    {
    }

    /**
     * @return bool
     */
    public function acquire(): bool
    {

        $tmpdir = sys_get_temp_dir();
        $bs = chr(92);
        $cname = str_replace($bs, '.', $this->lockname);
        $this->lockfilename = $tmpdir . DIRECTORY_SEPARATOR . $cname . '.lock';

        $this->lockfilepointer = fopen($this->lockfilename, "w+");

        $this->locked = flock($this->lockfilepointer, LOCK_EX | LOCK_NB);

        if (!$this->locked)
            fclose($this->lockfilepointer);

        return $this->locked;

    }

    public function release()
    {
        if ($this->locked) {
            flock($this->lockfilepointer, LOCK_UN); // release the lock
            fclose($this->lockfilepointer);
        }

        unlink($this->lockfilename);
    }


}
