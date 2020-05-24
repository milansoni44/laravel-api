<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\LoginController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

// Users
Route::prefix('/user')->group(function(){

    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/register', [LoginController::class, 'register']);

    // Authenticated routes
    Route::group(['middleware' => ['auth:api']], function () {
        // dd('milan');
        Route::get('/wallet', [LoginController::class, 'get_wallet']);
        Route::post('/credit', [LoginController::class, 'credit']);     
    });
    
});

