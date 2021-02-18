<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\Library;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Imtigger\LaravelJobStatus\Trackable;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

class SyncLibraryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Trackable, UsesLocks;

    public const DETECTION_MODE_MTIME = 'mtime';
    public const DETECTION_MODE_HASH = 'hash';

    /**
     * Create a new job instance.
     *
     * @param Library $library
     * @param bool $checkForChangesOnly Don't import or sync documents but only check if the library needs a sync
     * @param string $detectionMode hash is slower
     */
    public function __construct(
        protected Library $library,
        protected bool $checkForChangesOnly = false,
        protected string $detectionMode = self::DETECTION_MODE_MTIME
    )
    {
        $this->prepareStatus();
        $this->prepareLock($this->library, 1800);
    }

    public function handle()
    {
        // Wait max. 30 mins for the locks to get released
        $lock = $this->restoreLock($this->library);
        $lock->block(1800);

        exec('sudo /app/scripts/fix-permissions.sh');

        $libraryDirectoryIterator = new RecursiveDirectoryIterator($this->library->getAbsolutePath(), RecursiveDirectoryIterator::SKIP_DOTS);
        $libraryIterator = new RecursiveIteratorIterator($libraryDirectoryIterator);

        $changedDocuments = [];
        $jobs = [];
        $changesDetected = false; // For dry run

        $files = collect($libraryIterator);
        $this->setProgressMax(count($files) + 1);

        /** @var SplFileInfo $info */
        foreach ($files as $info) {
            $this->checkFile($info, $changesDetected, $changedDocuments, $jobs);
            $this->incrementProgress(1, max(count($files) / 100, 1));
        }

        $this->setProgressNow($this->progressMax - 1);

        // Now we have to get all document records and check if files are still there
        /** @var Document $document */
        foreach ($this->library->documents()->cursor() as $document) {
            if (!$document->existsInFilesystem()) {
                if ($this->checkForChangesOnly) {
                    $changesDetected = true;
                    $document->needs_sync = true;
                    $document->save();
                } else {
                    $document->delete();
                }
            }
        }

        if ($this->checkForChangesOnly) {
            $this->library->needs_sync = $changesDetected;
            $this->library->save();
        } else {
            $this->library->needs_sync = false;
            $this->library->save();
        }

        $this->setProgressNow($this->progressMax);

        foreach ($jobs as $job) {
            dispatch($job);
        }

        if (!$this->checkForChangesOnly) {
            $this->setOutput([
                'started_job_ids' => collect($jobs)->map(fn($job) => $job->getJobStatusId()),
            ]);
        }

        $lock->release();
    }

    /**
     * @param SplFileInfo $info
     * @param $changesDetected
     * @param $changedDocuments
     * @param $jobs
     * @throws Exception
     */
    public function checkFile(SplFileInfo $info, &$changesDetected, &$changedDocuments, &$jobs)
    {
        $absolutePath = $info->getPathname();
        $path = $this->library->getRelativePath($info->getRealPath());

        $mtime = Carbon::createFromTimestamp($info->getMTime());
        $computedHash = null;
        $getHash = function () use ($absolutePath, &$computedHash) {
            if ($computedHash === null) {
                $computedHash = Document::hashFile($absolutePath);
            }
            return $computedHash;
        };

        // Check if we have stored a document in DB at this path...
        /** @var Document $existingDocument */
        $existingDocument = $this->library->documents()->where('path', $path)->first();

        if ($existingDocument !== null) {
            $documentLock = null;

            // If we are only checking for changes
            $documentLock = $existingDocument->makeLock(600);
            $documentLock->block(600);

            try {
                // We already have a record in our database, so let's update
                // the hash if necessary…
                if (
                    ($this->detectionMode === self::DETECTION_MODE_MTIME
                        ? $existingDocument->last_mtime->notEqualTo($mtime)
                        : $existingDocument->last_hash !== $getHash()) ||
                    $existingDocument->needs_sync
                ) {
                    if ($this->checkForChangesOnly) {
                        $changesDetected = true;
                        $existingDocument->needs_sync = true;
                        $existingDocument->save();

                        if ($documentLock) {
                            $documentLock->release();
                        }
                    } else {
                        $existingDocument->last_hash = $getHash();
                        $existingDocument->last_mtime = $mtime;
                        $existingDocument->needs_sync = false;
                        $existingDocument->save();
                        $changedDocuments[] = $existingDocument->only(['id', 'path', 'title']);
                        $jobs[] = new GenerateThumbnailsJob($existingDocument);
                        $jobs[] = new GenerateOCRJob($existingDocument);
                    }
                }
            } catch (Exception $exception) {
                $documentLock->release();
                throw $exception;
            }
        } else if ($this->checkForChangesOnly) {
            $changesDetected = true;
        } else {
            // We didn't find a document record for this path. There are now multiple possibilities:
            // 1. it's a brand new document → create a record
            // 2. the document has been moved → find the old record and update its path

            // Check for possibility 2:
            // Find the first document record with the same hash, that does not exist in fileystem
            $movedDocument = null;
            /** @var Document $document */
            foreach ($this->library->documents()->where('last_hash', $getHash())->get() as $document) {
                if (!$document->existsInFilesystem()) {
                    $movedDocument = $document;
                }
            }

            if ($movedDocument) {
                // If our search for a old document record was successful…
                $movedDocument->path = $path;
                $movedDocument->needs_sync = false;
                $movedDocument->save();
                $changedDocuments[] = $movedDocument->only(['id', 'path', 'title']);
            } else {
                // We didn't find a non-existent document record with the same hash,
                // so this is likely a new file
                $document = new Document();
                $document->title = basename($absolutePath);
                $document->path = $path;
                $document->last_hash = $getHash();
                $document->last_mtime = $mtime;
                $this->library->documents()->save($document);
                $changedDocuments[] = $document->only(['id', 'path', 'title']);
                $jobs[] = new GenerateThumbnailsJob($document);
                $jobs[] = new GenerateOCRJob($document);
            }
        }
    }

    public function failed(Throwable $exception)
    {
        $this->restoreLock($this->library)->release();
        $this->library->needs_sync = true;
        $this->library->save();
    }
}
