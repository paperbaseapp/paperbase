<?php

namespace App\Models;

use App\Models\Traits\UsesPrimaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Ramsey\Collection\Collection;

/**
 * Class Library
 * @package App\Models
 * @property string id
 * @property string name
 * @property User owner
 * @property Document[]|Collection documents
 */
class Library extends Model
{
    use HasFactory;
    use UsesPrimaryUuid;

    protected static function booted()
    {
        static::created(function (Library $library) {
            mkdir($library->getAbsolutePath());
        });
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function getAbsolutePath()
    {
        return join_path(storage_path('libraries'), $this->id . '-' . Str::slug($this->name));
    }
}
