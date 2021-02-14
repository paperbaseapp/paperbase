<?php


namespace App\Http\Controllers;


use App\Jobs\SyncLibraryJob;
use App\Models\Library;
use App\Models\Stateless\LibraryNode;
use App\Models\User;
use Imtigger\LaravelJobStatus\JobStatus;
use SplFileInfo;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mime\MimeTypes;

class LibraryController extends Controller
{
    public function create()
    {
        $data = $this->validateWith([
            'name' => 'string|unique:libraries,name|required',
        ]);

        $library = new Library();
        $library->name = $data['name'];
        $library->owner()->associate(User::current());
        $library->save();

        return response()->json($library);
    }

    public function getAll()
    {
        return User::current()->libraries->sortBy('name', SORT_NATURAL|SORT_FLAG_CASE)->values();
    }

    public function get(Library $library)
    {
        return $library;
    }

    public function sync(Library $library)
    {
        $job = new SyncLibraryJob($library);
        dispatch($job);
        return response()->json(JobStatus::query()->whereKey($job->getJobStatusId())->firstOrFail());
    }

    public function browse(Library $library, string $path = '')
    {
        $items = collect($library->browseDirectory($path))
            ->map->jsonSerialize()
            ->sortBy('basename', SORT_NATURAL | SORT_FLAG_CASE)
            ->sortBy('type', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $parentPath = $library->getParentRelativePath($path);

        return response()->json([
            'items' => $items,
            'parent_path' => $parentPath,
        ]);
    }

    public function downloadFile(Library $library, string $path)
    {
        if ($library->hasFile($path)) {
            $fileInfo = new SplFileInfo($library->getAbsolutePath($path));
            return response()->download($fileInfo, $fileInfo->getBasename(), [
                'Content-Type' => MimeTypes::getDefault()->guessMimeType($library->getAbsolutePath($path)),
            ], 'inline');
        }

        throw new NotFoundHttpException('File not found');
    }
}

