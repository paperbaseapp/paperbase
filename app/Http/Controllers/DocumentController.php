<?php


namespace App\Http\Controllers;


use App\Models\Document;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocumentController extends Controller
{
    public function get(Document $document)
    {
        return $document;
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
