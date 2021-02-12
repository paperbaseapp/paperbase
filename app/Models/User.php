<?php

namespace App\Models;

use App\Models\Traits\HasSubscriptions;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;

/**
 * Class User
 * @package App\Models
 * @property string account
 * @property string password
 */
class User extends BaseModel implements Authenticatable
{
    use AuthenticatableTrait;
    use Notifiable;
    use Authorizable;
    use HasApiTokens;

    protected $visible = [
        'account',
        'name',
    ];

    public static function current(): ?User
    {
        return Auth::user();
    }
}
