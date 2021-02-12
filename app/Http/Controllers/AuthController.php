<?php


namespace App\Http\Controllers;


use App\Models\AuthenticatedUser;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthController extends Controller
{
    public function login()
    {
        $data = $this->validateWith([
            'account' => 'string|required',
            'password' => 'string|required',
            'remember' => 'boolean|sometimes',
        ]);

        if (Auth::attempt(Arr::only($data, ['account', 'password']), $data['remember'])) {
            session()->regenerate();
            return response()->json([
                'user' => User::current(),
            ]);
        }

        throw new AccessDeniedHttpException();
    }

    public function verify(?User $user)
    {
        if ($user) {
            return response()->json($user);
        }

        throw new AccessDeniedHttpException();
    }
}
