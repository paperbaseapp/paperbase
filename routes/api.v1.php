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


use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\LibraryController;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => '/auth'], function (Router $router) {
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/user', [AuthController::class, 'verify']);
});

Route::group(['middleware' => 'auth:sanctum'], function (Router $router) {
    $router->group(['prefix' => '/job'], function (Router $router) {
        $router->get('/batch', [JobController::class, 'getMultipleBatches'])->name('job/getMultipleBatches');
        $router->get('/{jobId}', [JobController::class, 'get']);
        $router->get('/', [JobController::class, 'getMultiple'])->name('job/getMultiple');
    });

    $router->group(['prefix' => '/library'], function (Router $router) {
        $router->post('/', [LibraryController::class, 'create']);
        $router->get('/', [LibraryController::class, 'getAll']);
        $router->get('/{library}', [LibraryController::class, 'get']);
        $router->post('/{library}/sync', [LibraryController::class, 'sync']);
        $router->get('/{library}/browse{path}', [LibraryController::class, 'browse'])->where('path', '(\/?.*)');
        $router->get('/{library}/download/{path}', [LibraryController::class, 'downloadFile'])->where('path', '(.+)');
        $router->get('/{library}/search', [LibraryController::class, 'search'])->name('search');
    });

    $router->group(['prefix' => '/document'], function (Router $router) {
        $router->get('/{document}', [DocumentController::class, 'get']);
        $router->get('/{document}/thumbnail', [DocumentController::class, 'getThumbnail'])->name('document/thumbnail');
    });
});
