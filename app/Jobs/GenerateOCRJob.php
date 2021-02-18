<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\DocumentPage;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Imtigger\LaravelJobStatus\Trackable;
use Symfony\Component\Process\Process;
use Throwable;

class GenerateOCRJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Trackable, UsesLocks;

    /**
     * Create a new job instance.
     *
     * @param Document $document
     */
    public function __construct(
        protected Document $document,
    )
    {
        $this->prepareStatus();
        $this->prepareLock($this->document, 600);
    }

    /**
     * @throws Exception
     */
    public function handle()
    {
        // Wait up to 10 minutes to get the document lock
        $lock = $this->restoreLock($this->document);
        $lock->block(600);

        if (Str::of($this->document->getAbsolutePath())->lower()->endsWith('.pdf')) {
            $pages = $this->readPdfPages();

            if (empty($pages)) {
                $process = new Process([
                    'ocrmypdf',
                    '-l',
                    'deu+eng', // TODO: Implement custom language from document
                    '--deskew',
                    '--output-type',
                    'pdfa',
                    '--force-ocr',
                    '--jobs',
                    '1',
                    $this->document->getAbsolutePath(),
                    $this->document->getAbsolutePath(),
                ]);
                $process->setTimeout(600);
                $process->start();
                $process->wait();

                if (!$process->isSuccessful()) {
                    throw new Exception('Could not generate OCR for ' . $this->document->title . "\n" . $process->getErrorOutput());
                }

                $this->document->last_hash = Document::hashFile($this->document->getAbsolutePath());
                $this->document->last_mtime = Carbon::createFromTimestamp($this->document->getFileInfo()->getMTime());
                $this->document->ocr_status = Document::OCR_DONE;
                $pages = $this->readPdfPages();
            } else {
                $this->document->ocr_status = Document::OCR_NOT_REQUIRED;
            }

            $this->document->pages()->delete();
            $this->document->pages()->saveMany($pages);
            $this->document->save();
        } else {
            $this->document->ocr_status = Document::OCR_UNAVAILABLE;
            $this->document->save();
        }

        $lock->release();
    }

    /**
     * @return DocumentPage[]
     * @throws Exception
     */
    public function readPdfPages(): array
    {
        $process = new Process([
            'pdftotext',
            $this->document->getAbsolutePath(),
            '-',
        ]);
        $process->setTimeout(600);
        $process->start();
        $process->wait();

        if (!$process->isSuccessful()) {
            throw new Exception('Could not generate OCR for ' . $this->document->title . "\n" . $process->getErrorOutput());
        }

        $pages = [];

        // pdftotext prints a form feed character (\f) after every page
        $pageNumber = 1;
        foreach (explode("\f", $process->getOutput()) as $pageText) {
            $text = trim($pageText);

            if (!empty($text)) {
                $pages[] = new DocumentPage([
                    'text_content' => $text,
                    'page' => $pageNumber,
                ]);
            }

            $pageNumber++;
        }

        return $pages;
    }

    public function failed(Throwable $exception)
    {
        $this->restoreLock($this->document)->release();
        $this->document->ocr_status = Document::OCR_FAILED;
        $this->document->save();
    }
}
