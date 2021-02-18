<?php

namespace App\Jobs;

use App\Models\Document;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Imtigger\LaravelJobStatus\Trackable;
use Symfony\Component\Process\Process;

class GenerateThumbnailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Trackable;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected Document $document,
    )
    {
        $this->prepareStatus();
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        if (Str::of($this->document->getAbsolutePath())->lower()->endsWith('.pdf')) {
            $process = new Process([
                'pdftocairo',
                $this->document->getAbsolutePath(),
                '-',
                '-jpeg',
                '-singlefile',
                '-antialias',
                'best',
                '-scale-to',
                '400',
                '-jpegopt',
                'quality=60,progressive=y,optimize=y',
            ]);
            $process->setTimeout(10);
            $process->start();
            $process->wait();

            if (!$process->isSuccessful()) {
                throw new Exception('Could not generate Thumbnail for ' . $this->document->title . "\n" . $process->getErrorOutput());
            }

            file_put_contents($this->document->getThumbnailPath(), $process->getOutput());
        }
    }
}
