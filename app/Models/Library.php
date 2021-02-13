<?php

namespace App\Models;

use App\Models\Traits\UsesPrimaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Collection\Collection;

/**
 * Class Library
 * @package App\Models
 * @property string id
 * @property string name
 * @property Document[]|Collection documents
 */
class Library extends Model
{
    use HasFactory;
    use UsesPrimaryUuid;

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function getAbsolutePath()
    {
        return join_path(storage_path('libraries'), $this->id);
    }
}
