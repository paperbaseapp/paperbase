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
    $router->post('/logout', [AuthController::class, 'logout']);
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
        $router->get('/{library}/node', [LibraryController::class, 'getNode']);
        $router->delete('/{library}/node', [LibraryController::class, 'deleteNode']);
        $router->get('/{library}/browse', [LibraryController::class, 'browse']);
        $router->get('/{library}/download/{fakeName}', [LibraryController::class, 'downloadFile'])->where('fakeName', '(.+)');
        $router->get('/{library}/search', [LibraryController::class, 'search'])->name('search');
        $router->post('/{library}/directory', [LibraryController::class, 'createDirectory']);
        $router->post('/{library}/file', [LibraryController::class, 'uploadFile']);
        $router->post('/{library}/node/rename', [LibraryController::class, 'renameNode']);
    });

    $router->group(['prefix' => '/document'], function (Router $router) {
        $router->get('/{document}', [DocumentController::class, 'get']);
        $router->get('/{document}/thumbnail', [DocumentController::class, 'getThumbnail'])->name('document/thumbnail');
        $router->post('/{document}/force-ocr', [DocumentController::class, 'forceOcr']);
    });
});
