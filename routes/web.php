<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/


use Illuminate\Support\Facades\Route;

// Don't add this rule in development. 404s are easier to debug without it.
if (app()->environment('production')) {
    Route::get('/{any}', function () {
        return file_get_contents(public_path('ui-index.html'));
    })->where('any', '.*');
}


