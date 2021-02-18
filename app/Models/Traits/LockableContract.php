<?php


namespace App\Models\Traits;



use Illuminate\Contracts\Cache\Lock;

interface LockableContract
{
    public function getLockName(): string;
    public function makeLock(int $seconds = 0): Lock;
    public function restoreLock(string $owner): Lock;
}
