<?php


namespace App\Jobs;


use App\Models\Traits\LockableContract;
use Illuminate\Contracts\Cache\Lock;

trait UsesLocks
{
    protected string $lockOwner;

    protected function prepareLock(LockableContract $lockable, int $seconds = 0)
    {
        $lock = $lockable->makeLock($seconds);
        $this->lockOwner = $lock->owner();
        return $lock;
    }

    protected function restoreLock(LockableContract $lockable): Lock
    {
        return $lockable->restoreLock($this->lockOwner);
    }
}
