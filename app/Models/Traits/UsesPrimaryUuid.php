<?php


namespace App\Models\Traits;


use Illuminate\Support\Str;

/**
 * Trait UsesPrimaryUuid
 * @package App\Models\Traits
 * @property string id
 */
trait UsesPrimaryUuid
{
    protected static function bootUsesPrimaryUuid()
    {
        static::creating(function ($model) {
            if (!$model->getKey()) {
                $model->{$model->getKeyName()} = (string)Str::uuid();
            }
        });
    }

    public function getIncrementing()
    {
        return false;
    }

    public function getKeyType()
    {
        return 'string';
    }
}
