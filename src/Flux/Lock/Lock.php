<?php
declare(strict_types=1);

namespace Flux\Lock;

class Lock implements LockInterface
{

    protected mixed $lockfilepointer = false;    // false|resource, result from fopen()
    protected string $lockfilename = '';
    protected bool $locked = false;

    public function __construct(protected string $lockname = '', protected int $expire = 0)
    {
    }

    public function acquire(): bool
    {

        $tmpdir = sys_get_temp_dir();
        $bs = chr(92);
        $cname = str_replace($bs, '.', $this->lockname);
        $this->lockfilename = $tmpdir . DIRECTORY_SEPARATOR . $cname . '.lock';

        $this->lockfilepointer = fopen($this->lockfilename, "w+");

        // cannot open file
        if ($this->lockfilepointer === false)
            return false;

        $this->locked = flock($this->lockfilepointer, LOCK_EX | LOCK_NB);

        if (!$this->locked)
            fclose($this->lockfilepointer);

        return $this->locked;

    }

    public function release(): void
    {
        if ($this->locked) {
            flock($this->lockfilepointer, LOCK_UN); // release the lock
            fclose($this->lockfilepointer);
        }

        if ($this->lockfilepointer !== false) {
            unlink($this->lockfilename);
        }

    }


}
