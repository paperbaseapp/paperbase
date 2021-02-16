<?php


namespace App\Jobs;


use Exception;
use Throwable;

trait Failsafe
{
    public function handle()
    {
        try {
            $this->failsafeHandle();
        } catch (Exception $exception) {
            $this->failed($exception);
            throw $exception;
        }
    }

    public function failsafeHandle()
    {
        // TBF
    }

    public function failed(Throwable $exception)
    {
        // TBF
    }
}
