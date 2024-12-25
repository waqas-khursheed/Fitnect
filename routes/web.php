<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\HomeController as AdminHomeController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('home', function () {
    return view('home');
});

Route::get('thankyou', function () {
    return view('thankyou');
});

Route::get('phpinfo', function () {
    phpinfo();
});

Route::controller(HomeController::class)->group( function(){
    Route::get('content/{type}', 'content');
});



// ADMIN ROUTES
Route::prefix('admin')->group(function () {  

    Route::controller(AdminAuthController::class)->group( function(){
        Route::get('/', 'loginFrom');
        Route::get('login', 'loginFrom');
        Route::post('login', 'login');
    });

    Route::group(['middleware'=>'auth'], function(){
        Route::controller(AdminHomeController::class)->group( function(){
            Route::get('dashboard', 'dashboard');
            // Route::get('users', 'usersList');
            Route::get('help-and-feedback', 'helpAndFeedback');

            Route::prefix('users')->group(function () {  
                Route::get('/', 'usersList');
                Route::get('block/{id}/{is_block}', 'userBlock');
            });


            Route::prefix('interests')->group(function () {  
                Route::get('/', 'interestList');
                Route::get('form', 'interestForm');
                Route::post('form', 'interestFormSubmit');
                Route::get('status/{id}/{status}', 'interestStatus');
            });

            Route::prefix('appointments')->group(function () {  
                Route::get('/', 'appointmentList');
            });


            Route::prefix('content')->group(function () {  
                Route::get('/{type}', 'getContent');
                Route::post('update/{type}', 'updateContent');
            });
        });

        Route::get('logout', [AdminAuthController::class, 'logout']);
    });

});
// ADMIN ROUTES END

Route::get('unauthorize', function () {
    return response()->json([
        'status' => 0, 
        'message' => 'Sorry User is Unauthorize'
    ], 401);
})->name('unauthorize');
