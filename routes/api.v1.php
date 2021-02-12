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


use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Providers\WebPushSubscriptionController;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => '/auth'], function (Router $router) {
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/user', [AuthController::class, 'verify']);
});

Route::group(['middleware' => 'auth:sanctum'], function (Router $router) {
    // Routes
});
