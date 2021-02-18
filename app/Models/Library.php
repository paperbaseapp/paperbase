<?php

namespace App\Models;

use App\Models\Stateless\LibraryNode;
use App\Models\Traits\Lockable;
use App\Models\Traits\LockableContract;
use App\Models\Traits\UsesPrimaryUuid;
use DirectoryIterator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Ramsey\Collection\Collection;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class Library
 * @package App\Models
 * @property string id
 * @property string name
 * @property bool needs_sync
 * @property User owner
 * @property Document[]|Collection documents
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
            Log::info($library->getAbsolutePath());
            mkdir($library->getAbsolutePath());
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

    public function getAbsolutePath(?string $toFile = null)
    {
        return $toFile === null
            ? canonicalize_path(join_path(storage_path('libraries'), $this->id . '-' . Str::slug($this->name)))
            : canonicalize_path(join_path($this->getAbsolutePath(), $toFile));
    }

    public function getRelativePath(string $absoluteFilePath)
    {
        $absoluteFilePath = canonicalize_path($absoluteFilePath);

        if (!str_starts_with($absoluteFilePath, $this->getAbsolutePath())) {
            throw new InvalidArgumentException('File ' . $absoluteFilePath . ' is not inside library ' . $this->id);
        }

        return ltrim(
            rtrim(app(Filesystem::class)->makePathRelative($absoluteFilePath, $this->getAbsolutePath()), '/'),
            '.',
        );
    }

    public function browseDirectory(string $relativePath): \Generator
    {
        $path = $this->getAbsolutePath($relativePath);

        if (!is_dir($path)) {
            throw new InvalidArgumentException('directoryPath must be a directory');
        }

        $iterator = new DirectoryIterator($path);


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

    public function hasFile(string $relativePath)
    {
        return is_file($this->getAbsolutePath($relativePath));
    }

    public function hasDirectory(string $relativePath)
    {
        return is_dir($this->getAbsolutePath($relativePath));
    }
}
