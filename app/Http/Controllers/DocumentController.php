<?php


namespace App\Http\Controllers;


use App\Jobs\GenerateOCRJob;
use App\Jobs\SyncLibraryJob;
use App\Models\Document;
use Imtigger\LaravelJobStatus\JobStatus;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocumentController extends Controller
{
    public function get(Document $document)
    {
        return $document;
    }

    public function forceOcr(Document $document)
    {
        $job = new GenerateOCRJob($document, true);
        dispatch($job);
        return response()->json(JobStatus::query()->whereKey($job->getJobStatusId())->firstOrFail());
    }

    public function getThumbnail(Document $document)
    {
        if (!$document->hasThumbnail()) {
            throw new NotFoundHttpException('Document has no thumbnail');
        }

        return response()->stream(function () use ($document) {
            echo file_get_contents($document->getThumbnailPath());
        }, headers: [
            'Content-Type' => 'image/jpeg',
        ]);
    }
}
