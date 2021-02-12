<?php


namespace App\Models\Traits;


use DateTimeInterface;

trait SerializesDatesToISO8601
{
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('c');
    }
}
