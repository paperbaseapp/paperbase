<?php


namespace App\Http\Controllers;


use App\Jobs\SyncLibraryJob;
use App\Models\DocumentPage;
use App\Models\Library;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Imtigger\LaravelJobStatus\JobStatus;
use MeiliSearch\Client as MeilisearchClient;
use MeiliSearch\Endpoints\Indexes;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        DB::transaction(fn() => $library->save());

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

    public function getNode(Library $library, string $path = '')
    {
        return $library->getLibraryNodeAt($path);
    }

    public function downloadFile(Library $library, string $path)
    {
        if ($library->hasFile($path)) {
            $node = $library->getLibraryNodeAt($path);
            $cacheOptions = [];

            if ($document = $node->getDocument()) {
                $cacheOptions['etag'] = $document->last_hash;
            }

            return response()->download($node->getFileInfo(), $node->getFileInfo()->getBasename(), [
                'Content-Type' => $node->getMime(),
            ], 'inline')->setCache($cacheOptions);
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

    public function deleteNode(Library $library, string $path)
    {
        $this->validateWith([
            'delete_permanently' => 'boolean|sometimes',
        ]);

        $node = $library->getLibraryNodeAt($path);

        if (request('delete_permanently')) {
            $node->delete();
        } else {
            $node->moveToTrash();
        }

        return response()->noContent();
    }

    public function uploadFile(Library $library, string $path)
    {
        $this->validateWith([
            'file' => 'file|required',
        ]);

        $file = request()->file('file');

        if (empty($file->getClientOriginalName())) {
            throw new BadRequestHttpException('Client did not supply a file name');
        }

        $targetFile = join_path($path, $file->getClientOriginalName());

        if (!$library->isInsideLibrary($targetFile)) {
            throw new BadRequestHttpException('Target file must be inside library');
        }

        if ($library->hasNode($targetFile)) {

        }
    }

    public function createDirectory(Library $library, string $path)
    {
        $newDirectoryPath = $library->getRelativePath($library->getAbsolutePath($path));
        mkdir($library->getAbsolutePath($newDirectoryPath), recursive: true);

        return $library->getLibraryNodeAt($newDirectoryPath);
    }
}

