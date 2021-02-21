<?php

namespace App\Models;

use App\Models\Traits\UsesPrimaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * Class DocumentPage
 * @package App\Models
 * @property string text_content
 * @property int page
 * @property Document document
 */
class DocumentPage extends Model
{
    use UsesPrimaryUuid;
    use Searchable;

    protected $fillable = [
        'text_content',
        'page',
    ];

    protected $appends = [
        'search_metadata',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function getSearchMetadataAttribute()
    {
        return $this->scoutMetadata();
    }

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'library_id' => $this->document->library_id,
            //                                      ↓ private unicode range, some PDFs are weird
            'text_content' => preg_replace('/[\x{e000}-\x{f8ff}]+/u', '', $this->text_content),
            'document_title' => $this->document->title,
            'document_filename' => $this->document->getFileInfo()->getBasename(),
        ];
    }
}
