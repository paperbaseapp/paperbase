<?php


namespace App\Models\Traits;


use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

trait Lockable
{
    public function getLockName(): string
    {
        return $this->getTable() . '.' . $this->id;
    }

    public function makeLock(int $seconds = 0): Lock
    {
        return Cache::lock($this->getLockName(), $seconds);
    }

    public function restoreLock(string $owner): Lock
    {
        return Cache::restoreLock($this->getLockName(), $owner);
    }

    public function forceReleaseLock()
    {
        $this->makeLock()->forceRelease();
    }
}
