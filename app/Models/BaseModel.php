<?php


namespace App\Models;


use App\Models\Traits\SerializesDatesToISO8601;
use App\Models\Traits\UsesPrimaryUuid;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use UsesPrimaryUuid;
    use SerializesDatesToISO8601;
}
