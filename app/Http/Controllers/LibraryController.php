<?php


namespace App\Http\Controllers;


use App\Jobs\SyncLibraryJob;
use App\Models\DocumentPage;
use App\Models\Library;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Imtigger\LaravelJobStatus\JobStatus;
use MeiliSearch\Client as MeilisearchClient;
use MeiliSearch\Endpoints\Indexes;
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
        return User::current()->libraries->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
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

    /**
     * @param Library $library
     * @param MeilisearchClient $client
     * @return array|\Illuminate\Database\Eloquent\Collection
     */
    public function search(Library $library, MeilisearchClient $client)
    {
        if (!collect($client->getAllIndexes())->some(fn(Indexes $index) => $index->getUid() === (new DocumentPage())->searchableAs())) {
            return [];
        }

        $query = request('query', '');
        return DocumentPage::search($query, function (Indexes $meilisearch, $query, $options) use ($library) {
            $options['filters'] = 'library_id="' . $library->id . '"';
            $options['attributesToCrop'] = ['text_content:50'];
            $options['attributesToHighlight'] = ['text_content', 'document_title'];
            return $meilisearch->search($query, $options);
        })
            ->take(20)
            ->query(fn(Builder $query) => $query->with('document'))
            ->get()
            ->makeVisible('search_metadata');
    }
}

