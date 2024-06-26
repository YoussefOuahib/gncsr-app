<?php

use App\Http\Controllers\CredentialsController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\UserController;
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

Route::middleware('auth:sanctum')->get('/info/user', UserController::class);

// Route::get('dynamics/connect', [CredentialsController::class , 'connect']);
// Route::get('dynamics/members', [CredentialsController::class, 'getMembers']);

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('execute/{id}', [CredentialsController::class, 'execute']);
    Route::get('get/credentials', [CredentialsController::class, 'index']);
    Route::post('store/credentials', [CredentialsController::class, 'store']);
});
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('customers', CustomerController::class);
    Route::get('show/credentials/{id}', [CredentialsController::class, 'show']);
    Route::put('update/credentials/{id}', [CredentialsController::class, 'update']);

});