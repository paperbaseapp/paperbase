<?php

namespace App\Jobs;

use App\Models\Document;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Imtigger\LaravelJobStatus\Trackable;
use Symfony\Component\Process\Process;
use Throwable;

class GenerateThumbnailsJob extends SafeJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use UsesLocks;

    public $tries = 1000;
    public $timeout = 20;
    public $maxExceptions = 1;

    /**
     * Create a new job instance.
     *
     * @param Document $document
     */
    public function __construct(
        protected Document $document,
    )
    {
        $this->prepareLock($this->document, 20);
    }

    public function safeHandle()
    {
        if (Str::of($this->document->getAbsolutePath())->lower()->endsWith('.pdf')) {
            $lock = $this->restoreLock($this->document);

            if ($lock->get()) {
                $process = new Process([
                    'pdftocairo',
                    $this->document->getAbsolutePath(),
                    '-',
                    '-jpeg',
                    '-singlefile',
                    '-antialias',
                    'best',
                    '-scale-to',
                    '512',
                    '-jpegopt',
                    'quality=75,progressive=y,optimize=y',
                ]);
                $process->setTimeout(10);
                $process->start();
                $process->wait();

                if (!$process->isSuccessful()) {
                    throw new Exception('Could not generate Thumbnail for ' . $this->document->path . "\n" . $process->getErrorOutput());
                }

                file_put_contents($this->document->getThumbnailPath(), $process->getOutput());

                $lock->release();
            } else {
                $this->release(10);
            }
        }
    }

    public function onFail(\Throwable $exception): bool
    {
        $this->restoreLock($this->document)->release();
        return false;
    }
}
