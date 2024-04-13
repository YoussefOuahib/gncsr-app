<?php

use App\Http\Controllers\CredentialsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Route::get('dynamics/connect', [CredentialsController::class , 'connect']);
Route::post('dynamics/execute', [CredentialsController::class, 'execute']);
// Route::get('dynamics/members', [CredentialsController::class, 'getMembers']);

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('get/credentials', [CredentialsController::class, 'index']);
    Route::post('store/credentials', [CredentialsController::class, 'store']);
});
