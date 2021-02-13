<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\Library;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Imtigger\LaravelJobStatus\Trackable;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Filesystem\Filesystem;

class SyncLibraryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Trackable;

    /**
     * Create a new job instance.
     *
     * @param Library $library
     * @param bool $checkSyncNeededOnly Don't import or sync documents but only check if the library needs a sync
     */
    public function __construct(
        protected Library $library,
        protected bool $checkSyncNeededOnly = false,
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
        $filesystem = app(Filesystem::class);
        $libraryDirectoryIterator = new RecursiveDirectoryIterator($this->library->getAbsolutePath(), RecursiveDirectoryIterator::SKIP_DOTS);
        $libraryIterator = new RecursiveIteratorIterator($libraryDirectoryIterator);

        $changedDocuments = [];
        $changesDetected = false; // For dry run

        $files = collect($libraryIterator);
        $this->setProgressMax(count($files) + 1);

        /** @var \SplFileInfo $info */
        foreach ($files as $info) {
            $absolutePath = $info->getPathname();
            $path = rtrim($filesystem->makePathRelative($absolutePath, $this->library->getAbsolutePath()), '/');
            $hash = Document::hashFile($absolutePath);

            // Check if we have stored a document in DB at this path...
            /** @var Document $existingDocument */
            $existingDocument = $this->library->documents()->where('path', $path)->first();

            if ($existingDocument !== null) {
                // We already have a record in our database, so let's update
                // the hash if necessary…
                if ($existingDocument->last_hash !== $hash) {
                    if ($this->checkSyncNeededOnly) {
                        $changesDetected = true;
                        break;
                    } else {
                        $existingDocument->last_hash = $hash;
                        $existingDocument->save();
                        $changedDocuments[] = $existingDocument->only(['id', 'path', 'title']);
                        dispatch(new GenerateThumbnailsJob($existingDocument));
                    }
                }
            } else if ($this->checkSyncNeededOnly) {
                // There's a new file but we are checking the library dirty state
                // so just update $changesDetected and exit the loop
                $changesDetected = true;
                break;
            } else {
                // We didn't find a document record for this path. There are now multiple possibilities:
                // 1. it's a brand new document → create a record
                // 2. the document has been moved → find the old record and update its path

                // Check for possibility 2:
                // Find the first document record with the same hash, that does not exist in fileystem
                $movedDocument = null;
                /** @var Document $document */
                foreach ($this->library->documents()->where('last_hash', $hash)->get() as $document) {
                    if (!$document->existsInFilesystem()) {
                        $movedDocument = $document;
                    }
                }

                if ($movedDocument) {
                    // If our search for a old document record was successful…
                    $movedDocument->path = $path;
                    $movedDocument->save();
                    $changedDocuments[] = $movedDocument->only(['id', 'path', 'title']);
                } else {
                    // We didn't find a non-existent document record with the same hash,
                    // so this is likely a new file
                    $document = new Document();
                    $document->title = basename($absolutePath);
                    $document->path = $path;
                    $document->last_hash = $hash;
                    $this->library->documents()->save($document);
                    $changedDocuments[] = $document->only(['id', 'path', 'title']);
                }
            }

            $this->incrementProgress(1, max(count($files) / 100, 1));
        }

        $this->setProgressNow($this->progressMax - 1);

        // Now we have to get all document records and check if files are still there
        /** @var Document $document */
        foreach ($this->library->documents()->cursor() as $document) {
            if (!$document->existsInFilesystem()) {
                if ($this->checkSyncNeededOnly) {
                    $changesDetected = true;
                    break;
                } else {
                    $document->delete();
                }
            }
        }

        if ($this->checkSyncNeededOnly) {
            $this->library->needs_sync = $changesDetected;
            $this->library->save();
        } else {
            $this->library->needs_sync = false;
            $this->library->save();
        }

        $this->setProgressNow($this->progressMax);

        if (!$this->checkSyncNeededOnly) {
            $this->setOutput($changedDocuments);
        }
    }
}
