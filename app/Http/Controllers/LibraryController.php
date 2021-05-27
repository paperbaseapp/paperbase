<?php


namespace App\Http\Controllers;


use App\Jobs\SyncLibraryJob;
use App\Models\DocumentPage;
use App\Models\Library;
use App\Models\Stateless\LibraryNode;
use App\Models\User;
use App\Script;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Imtigger\LaravelJobStatus\JobStatus;
use MeiliSearch\Client as MeilisearchClient;
use MeiliSearch\Endpoints\Indexes;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

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

    public function browse(Library $library)
    {
        $this->validateWith(['path' => 'string|nullable']);
        $path = request('path', '/');

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

    public function getNode(Library $library)
    {
        $this->validateWith(['path' => 'string|nullable']);
        return $library->getLibraryNodeAt(request('path', '/'));
    }

    public function downloadFile(Library $library)
    {
        $this->validateWith(['path' => 'string|nullable']);
        $path = request('path', '/');

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

    public function deleteNode(Library $library)
    {
        $this->validateWith([
            'delete_permanently' => 'boolean|sometimes',
            'path' => 'string|nullable',
        ]);

        $path = request('path', '/');
        $node = $library->getLibraryNodeAt($path);

        if (request('delete_permanently')) {
            $node->delete();
        } else {
            $node->moveToTrash();
        }

        return response()->noContent();
    }

    public function uploadFile(Library $library)
    {
        $this->validateWith([
            'files' => 'array|required',
            'files.*' => 'file',
            'path' => 'string|nullable',
        ]);

        $path = request('path', '/');
        $files = request()->file('files');

        $documents = [];

        foreach ($files as $file) {
            if (empty($file->getClientOriginalName())) {
                throw new BadRequestHttpException('Client did not supply a file name');
            }

            $targetFile = join_path($path, $file->getClientOriginalName());

            if (!$library->isInsideLibrary($targetFile)) {
                throw new BadRequestHttpException('Target file must be inside library');
            }

            $targetFile = $library->getSafeFilename($targetFile);

            rename($file->path(), $library->getAbsolutePath($targetFile));

            if (config('paperbase.library_directory_owner_uid') !== null) {
                Script::run('set-owner.sh', [
                    $library->getAbsolutePath($targetFile),
                    config('paperbase.library_directory_owner_uid'),
                ]);
            }

            $documents[] = $library
                ->addDocumentFromPath($library->getAbsolutePath($targetFile))
                ->dispatchPendingJobs();
        }

        return $documents;
    }

    /**
     * @throws \Safe\Exceptions\FilesystemException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function createDirectory(Library $library)
    {
        $this->validateWith(['path' => 'string|nullable']);
        $path = request('path', '/');

        if (!$library->isInsideLibrary($path)) {
            throw new BadRequestHttpException('Path must be inside library');
        }

        if ($library->hasNode($path)) {
            throw new ConflictHttpException();
        }

        \Safe\mkdir($library->getAbsolutePath($path), recursive: true);

        if (config('paperbase.library_directory_owner_uid') !== null) {
            Script::run('set-owner.sh', [
                $library->getAbsolutePath($path),
                config('paperbase.library_directory_owner_uid'),
            ]);
        }

        return $library->getLibraryNodeAt($path);
    }

    /**
     * @throws \Safe\Exceptions\FilesystemException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function renameNode(Library $library)
    {
        $this->validateWith([
            'source_path' => 'string|nullable',
            'target_path' => 'string|nullable',
            'move_inside_directories' => 'boolean|sometimes', // Whether to move source into target, if target is an existing directory
        ]);

        $sourceNode = $library->getLibraryNodeAt(request('source_path', ''));
        $targetPath = request('target_path', '');

        if (!$library->isInsideLibrary($targetPath)) {
            throw new BadRequestHttpException('Target path must be inside library');
        }

        if ($library->hasNode($targetPath)) {
            $targetNode = $library->getLibraryNodeAt(request('target_path', '/'));

            if ($targetNode->getType() === LibraryNode::TYPE_DIRECTORY && request('move_inside_directories', false)) {
                $targetPath = join_path($targetPath, $sourceNode->getFileInfo()->getBasename());
            }
        }

        $sourceNode->rename($library->getSafeFilename($targetPath));

        return $sourceNode;
    }
}

