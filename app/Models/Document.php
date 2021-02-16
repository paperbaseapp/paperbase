<?php

namespace App\Models;

use App\Jobs\GenerateOCRJob;
use App\Jobs\GenerateThumbnailsJob;
use App\Models\Traits\UsesPrimaryUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SplFileInfo;

/**
 * Class Document
 * @package App\Models
 * @property string id
 * @property string path The path inside the library
 * @property string title
 * @property string last_hash
 * @property Carbon last_mtime
 * @property string ocr_status
 * @property Library library
 * @property bool needs_sync
 * @property string library_id
 */
class Document extends Model
{
    use HasFactory;
    use UsesPrimaryUuid;

    public const OCR_PENDING = 'pending';
    public const OCR_DONE = 'done';
    public const OCR_UNAVAILABLE = 'unavailable';
    public const OCR_FAILED = 'failed';
    public const OCR_NOT_REQUIRED = 'not_required';

    protected $appends = [
        'thumbnail_url',
        'directory_path',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'last_mtime',
    ];

    protected $visible = [
        'id',
        'path',
        'title',
        'library_id',
        'last_hash',
        'thumbnail_url',
        'directory_path',
    ];

    protected static function booted()
    {
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

    public static function hashFile(string $path): string
    {
        return hash_file('sha256', $path);
    }

    public function getLock(int $seconds = 0)
    {
        return Cache::lock('document.' . $this->id, $seconds);
    }
}
