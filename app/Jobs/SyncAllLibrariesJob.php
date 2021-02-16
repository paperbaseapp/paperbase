<?php

namespace App\Jobs;

use App\Models\Library;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Imtigger\LaravelJobStatus\Trackable;

class SyncAllLibrariesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Trackable;

    /**
     * Create a new job instance.
     *
     * @param bool $checkSyncNeededOnly
     */
    public function __construct(protected bool $checkSyncNeededOnly = false)
    {
        $this->prepareStatus();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $libraries = Library::all();
        $this->setProgressMax(count($libraries));

        /** @var Library $library */
        foreach ($libraries as $library) {
            try {
                dispatch_now(new SyncLibraryJob($library, $this->checkSyncNeededOnly));
            } catch (\Exception $exception) {
                report($exception);
            }
            $this->incrementProgress();
        }
    }
}
