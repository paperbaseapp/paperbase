<?php

namespace App\Models;

use App\Models\Traits\UsesPrimaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use JetBrains\PhpStorm\Pure;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class Document
 * @package App\Models
 * @property string id
 * @property string path The path inside the library
 * @property string title
 * @property string text_content
 * @property string $last_hash
 * @property Library library
 */
class Document extends Model
{
    use HasFactory;
    use UsesPrimaryUuid;

    public const OCR_PENDING = 'pending';
    public const OCR_DONE = 'done';
    public const OCR_UNAVAILABLE = 'unavailable';

    protected $visible = [
        'id',
        'path',
        'title',
        'last_hash',
    ];

    public function library()
    {
        return $this->belongsTo(Library::class);
    }

    public function getAbsolutePath(): string
    {
        return join_path($this->library->getAbsolutePath(), $this->path);
    }

    public function existsInFilesystem()
    {
        return file_exists($this->getAbsolutePath());
    }

    public function getCalculatedHash(): string
    {
        return self::hashFile($this->getAbsolutePath());
    }

    public static function hashFile(string $path): string
    {
        return hash_file('sha256', $path);
    }
}
