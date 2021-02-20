<?php


namespace App;


use Symfony\Component\Process\Process;

abstract class Script
{
    public static function run(string $name, array $args, float $timeout = 20, bool $sudo = true)
    {
        $commandLine = collect();

        if ($sudo) {
            $commandLine->push('sudo');
        }

        $commandLine->push('/app/scripts/' . $name);
        $commandLine->push(...$args);

        $process = new Process($commandLine->values()->toArray());
        $process->setTimeout($timeout);
        $process->start();
        $process->wait();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Process failed: ' . $process->getErrorOutput());
        }
    }
}
