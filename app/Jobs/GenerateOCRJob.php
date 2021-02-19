<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\DocumentPage;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class GenerateOCRJob extends SafeJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use UsesLocks;

    public $maxExceptions = 1;
    public $tries = 1000;
    public $timeout;

    /**
     * Create a new job instance.
     *
     * @param Document $document
     */
    public function __construct(
        protected Document $document,
    )
    {
        $this->timeout = config('paperbase.ocr_timeout');
        $this->prepareLock($this->document, $this->timeout);
    }

    public function safeHandle()
    {
        // Wait up to 10 minutes to get the document lock
        $lock = $this->restoreLock($this->document);

        if (Str::of($this->document->getAbsolutePath())->lower()->endsWith('.pdf')) {
            if ($lock->get()) {
                $pages = $this->readPdfPages();

                if (empty($pages)) {
                    $process = new Process([
                        'ocrmypdf',
                        '-l',
                        'deu+eng', // TODO: Implement custom language from document
                        '--deskew',
                        '--output-type',
                        'pdfa',
                        '--jobs',
                        '2',
                        '--tesseract-timeout',
                        strval($this->timeout - 10), // Leave 10s for non-tesseract processing
                        $this->document->getAbsolutePath(),
                        $this->document->getAbsolutePath(),
                    ]);

                    $process->setTimeout(config('paperbase.ocr_timeout'));
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
                $lock->release();
            } else {
                $this->release(10);
            }

        } else {
            $this->document->ocr_status = Document::OCR_UNAVAILABLE;
            $this->document->save();
        }
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

    public function onFail(\Throwable $exception): bool
    {
        $this->restoreLock($this->document)->release();
        $this->document->ocr_status = Document::OCR_FAILED;
        $this->document->save();

        return false;
    }
}
