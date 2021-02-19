<?php


namespace App\Jobs;


use Illuminate\Support\Facades\Log;

abstract class SafeJob
{
    public abstract function safeHandle();

    public abstract function onFail(\Throwable $exception): bool;

    // Prevent overriding. onFail() must be used
    public final function failed(\Throwable $exception)
    {
        $this->onFail($exception);
    }

    public final function handle()
    {
        try {
            $this->safeHandle();
        } catch (\Throwable $exception) {
            if (!$this->onFail($exception)) {
                report($exception);
                throw $exception;
            }
        }
    }
}
