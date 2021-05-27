<?php

namespace App\Models;

use App\Models\Stateless\LibraryNode;
use App\Models\Traits\Lockable;
use App\Models\Traits\LockableContract;
use App\Models\Traits\UsesPrimaryUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use SplFileInfo;

/**
 * Class Document
 * @package App\Models
 * @property string id
 * @property string path The path inside the library
 * @property string|null title
 * @property string last_hash
 * @property Carbon last_mtime
 * @property string ocr_status
 * @property Library library
 * @property bool needs_sync
 * @property string library_id
 * @property string basename
 * @property Carbon trashed_at
 */
class Document extends Model implements LockableContract
{
    use HasFactory;
    use UsesPrimaryUuid;
    use Lockable;

    public const OCR_PENDING = 'pending';
    public const OCR_DONE = 'done';
    public const OCR_UNAVAILABLE = 'unavailable';
    public const OCR_FAILED = 'failed';
    public const OCR_NOT_REQUIRED = 'not_required';

    protected $appends = [
        'thumbnail_url',
        'directory_path',
        'basename',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'last_mtime',
        'trashed_at',
    ];

    protected $visible = [
        'id',
        'path',
        'basename',
        'title',
        'library_id',
        'last_hash',
        'thumbnail_url',
        'directory_path',
        'ocr_status',
        'created_at',
        'trashed_at',
    ];

    /** @var array Pending jobs for this document */
    protected array $pendingJobs = [];

    protected static function booted()
    {
        static::deleting(function (Document $document) {
            $document->pages()->each(function (DocumentPage $page) {
                $page->delete();
            });
        });

        static::deleted(function (Document $document) {
            if ($document->hasThumbnail()) {
                unlink($document->getThumbnailPath());
            }
        });
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return User::current()->documents()->where('documents.id', $value)->firstOrFail();
    }

    public function library()
    {
        return $this->belongsTo(Library::class);
    }

    public function pages()
    {
        return $this->hasMany(DocumentPage::class);
    }

    public function getLibraryNode(): LibraryNode
    {
        return $this->library->getLibraryNodeAt($this->path);
    }

    public function getDirectoryPathAttribute()
    {
        return Str::beforeLast($this->path, '/');
    }

    public function getAbsolutePath(): string
    {
        return join_path($this->library->getAbsolutePath(), $this->path);
    }

    public function getFileInfo(): SplFileInfo
    {
        return new SplFileInfo($this->getAbsolutePath());
    }

    public function getThumbnailPath(): string
    {
        return join_path(storage_path('thumbnails'), $this->id . '.jpg');
    }

    public function hasThumbnail()
    {
        return file_exists($this->getThumbnailPath());
    }

    public function existsInFilesystem()
    {
        return file_exists($this->getAbsolutePath());
    }

    public function getCalculatedHash(): string
    {
        return self::hashFile($this->getAbsolutePath());
    }

    public function getThumbnailUrlAttribute()
    {
        if (!$this->hasThumbnail()) {
            return null;
        }

        return route('document/thumbnail', ['document' => $this->id]);
    }

    public function getBasenameAttribute()
    {
        return basename($this->path);
    }

    public static function hashFile(string $path): string
    {
        return hash_file('sha256', $path);
    }

    public function addPendingJob($job)
    {
        $this->pendingJobs[] = $job;
    }

    public function getPendingJobs(): array
    {
        return $this->pendingJobs;
    }

    public function dispatchPendingJobs(): self
    {
        collect($this->pendingJobs)->each(fn($job) => dispatch($job));
        return $this;
    }

    /**
     * Make sure path does not start with a slash
     * @param string $path
     */
    public function setPathAttribute(string $path)
    {
        $this->attributes['path'] = ltrim($path, '/');
    }
}
