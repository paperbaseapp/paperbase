<?php

namespace App\Models;

use App\Jobs\GenerateOCRJob;
use App\Jobs\GenerateThumbnailsJob;
use App\Models\Stateless\LibraryNode;
use App\Models\Traits\Lockable;
use App\Models\Traits\LockableContract;
use App\Models\Traits\UsesPrimaryUuid;
use App\Script;
use Carbon\Carbon;
use DirectoryIterator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Ramsey\Collection\Collection;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class Library
 * @package App\Models
 * @property string id
 * @property string name
 * @property bool needs_sync
 * @property User owner
 * @property Document[]|Collection documents
 * @property string trash_path
 * @property string inbox_path
 */
class Library extends Model implements LockableContract
{
    use HasFactory;
    use UsesPrimaryUuid;
    use Lockable;

    protected $visible = [
        'id',
        'name',
        'needs_sync',
    ];

    protected static function booted()
    {
        static::created(function (Library $library) {
            $library->refresh();
            mkdir($library->getAbsolutePath());
            chmod($library->getAbsolutePath(), 0770);

            mkdir($library->getAbsolutePath($library->trash_path));
            mkdir($library->getAbsolutePath($library->inbox_path));
            chmod($library->getAbsolutePath($library->trash_path), 0770);
            chmod($library->getAbsolutePath($library->inbox_path), 0770);

            if (config('paperbase.library_directory_owner_uid') !== null) {
                Script::run('set-owner.sh', [
                    $library->getAbsolutePath(),
                    config('paperbase.library_directory_owner_uid'),
                ]);
            }
        });
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return User::current()->libraries()->where('id', $value)->firstOrFail();
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function getLibraryDirectoryName()
    {
        return $this->id . '-' . Str::slug($this->name);
    }

    public function getAbsolutePath(?string $toFile = null)
    {
        return $toFile === null
            ? canonicalize_path(join_path(storage_path('libraries'), $this->getLibraryDirectoryName()))
            : canonicalize_path(join_path($this->getAbsolutePath(), $toFile));
    }

    public function isInsideLibrary(string $path, bool $absolute = false)
    {
        return $absolute
            ? str_starts_with($path, $this->getAbsolutePath())
            : str_starts_with($this->getAbsolutePath($path), $this->getAbsolutePath());
    }

    public function getRelativePath(string $absoluteFilePath)
    {
        $absoluteFilePath = canonicalize_path($absoluteFilePath);

        if (!$this->isInsideLibrary($absoluteFilePath, true)) {
            throw new InvalidArgumentException('File ' . $absoluteFilePath . ' is not inside library ' . $this->id);
        }

        return preg_replace(
            '/(^\.\/|^\.$)/',
            '',
            rtrim(app(Filesystem::class)->makePathRelative($absoluteFilePath, $this->getAbsolutePath()), '/'),
        );
    }

    public function getLibraryNodeAt(string $relativePath): LibraryNode
    {
        $path = $this->getAbsolutePath($relativePath);

        if (!file_exists($path)) {
            throw new NotFoundHttpException();
        }

        return new LibraryNode($this, $this->getRelativePath($path));
    }

    /**
     * @param string $relativePath
     * @param bool $recursive
     * @return \Generator|LibraryNode[]
     */
    public function browseDirectory(string $relativePath, bool $recursive = false): \Generator
    {
        $path = $this->getAbsolutePath($relativePath);

        if (!file_exists($path)) {
            throw new NotFoundHttpException();
        }

        if (!is_dir($path)) {
            throw new InvalidArgumentException('directoryPath must be a directory');
        }

        if ($recursive) {
            $directoryIterator = new RecursiveDirectoryIterator($this->getAbsolutePath(), RecursiveDirectoryIterator::SKIP_DOTS);
            $iterator = new RecursiveIteratorIterator($directoryIterator);
        } else {
            $iterator = new DirectoryIterator($path);
        }

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (!$iterator->isDot()) {
                yield new LibraryNode($this, $this->getRelativePath($item->getRealPath()));
            }
        }
    }

    public function getParentRelativePath(string $relativePath)
    {
        $fileInfo = new SplFileInfo($this->getAbsolutePath($relativePath));
        return $this->isLibraryRootPath($relativePath)
            ? null
            : $this->getRelativePath($fileInfo->getPath());
    }

    public function isLibraryRootPath(string $relativePath)
    {
        return rtrim(canonicalize_path($this->getAbsolutePath($relativePath)), '/') === $this->getAbsolutePath();
    }

    public function hasNode(string $relativePath)
    {
        return file_exists($this->getAbsolutePath($relativePath));
    }

    public function hasFile(string $relativePath)
    {
        return is_file($this->getAbsolutePath($relativePath));
    }

    public function hasDirectory(string $relativePath)
    {
        return is_dir($this->getAbsolutePath($relativePath));
    }


    /**
     * Takes a path to a file or directory. If the target path
     * already exists, it appends a number to the target until
     * a non-existing path is found.
     */
    public function getSafeFilename(string $relativePath)
    {
        $counter = 1;

        if (!file_exists($this->getAbsolutePath($relativePath))) {
            return $relativePath;
        }

        $dirname = dirname($relativePath);
        $filename = basename($relativePath);

        do {
            $count = $counter++;
            $filenameSplit = explode('.', $filename);
            $targetFilenameSplit = [];

            for ($i = 0; $i < count($filenameSplit); $i++) {
                $targetFilenameSplit[] = $filenameSplit[$i];

                if ($i === max(count($filenameSplit) - 2, 0) && $count > 1) {
                    $targetFilenameSplit[count($targetFilenameSplit) - 1] .= " $count";
                }
            }

            $targetFilename = join('.', $targetFilenameSplit);
            $targetPath = join_path($dirname, $targetFilename);
        } while (file_exists($this->getAbsolutePath($targetPath)));

        return $targetPath;
    }

    public function getAvailableTrashPath(string $filename)
    {
        return $this->getSafeFilename(join_path($this->trash_path, $filename));
    }
}
