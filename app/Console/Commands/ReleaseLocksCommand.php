<?php

namespace App\Console\Commands;

use App\Jobs\OCRPDFJob;
use App\Models\Document;
use App\Models\Library;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ReleaseLocksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locks:release';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release all locks';


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Library::all()->each->forceReleaseLock();
        Document::all()->each->forceReleaseLock();
        Cache::lock('sync_all')->forceRelease();

        return 0;
    }
}
