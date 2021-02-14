<?php

namespace App\Jobs;

use App\Models\Document;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class GenerateOCRJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Failsafe;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected Document $document,
    )
    {

    }

    public function failsafeHandle()
    {
        if (Str::of($this->document->getAbsolutePath())->lower()->endsWith('.pdf')) {
            $text = $this->readPdfText();

            if (empty($text)) {
                $process = new Process([
                    'ocrmypdf',
                    '-l',
                    'deu+eng', // TODO: Implement custom language from document
                    '--deskew',
                    '--output-type',
                    'pdfa',
                    '--force-ocr',
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
                $text = $this->readPdfText();
            }

            $this->document->text_content = $text;
            $this->document->save();
        } else {
            $this->document->ocr_status = Document::OCR_UNAVAILABLE;
            $this->document->save();
        }
    }

    public function readPdfText(): string
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

        return trim($process->getOutput(), " \t\n\r\0\x0B\x0C");
    }

    public function failed(Throwable $exception)
    {
        $this->document->ocr_status = Document::OCR_FAILED;
        $this->document->save();
    }
}
