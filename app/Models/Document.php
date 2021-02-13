<?php

namespace App\Models;

use App\Models\Traits\UsesPrimaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Class Document
 * @package App\Models
 * @property string id
 * @property string path
 * @property string title
 * @property string text_content
 * @property Library library
 */
class Document extends Model
{
    use HasFactory;
    use UsesPrimaryUuid;

    protected $visible = [
        'id',
        'path',
        'title',
    ];

    public function library()
    {
        return $this->belongsTo(Library::class);
    }

    public function getAbsolutePath()
    {
        return join_path($this->library->getAbsolutePath(), $this->path);
    }

    public function calculateHash()
    {
        return hash_file('sha256', $this->getAbsolutePath());
    }
}
