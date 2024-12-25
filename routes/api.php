<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GeneralController;
use App\Http\Controllers\Api\SocialMediaController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RemoteMediaController;
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


Route::controller(AuthController::class)->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', 'login');
        Route::post('verification', 'verification');
        Route::post('re-send-code', 'reSendCode');
        Route::post('social-login', 'socialLogin');
    });

    Route::get('content', 'content');

    Route::controller(GeneralController::class)->group(function () {
        Route::prefix('general')->group(function () {
            Route::get('country', 'getCountry');
            Route::get('state', 'getState');
            Route::get('city', 'getCity');
        });
    });

    Route::group(['middleware' => 'auth:sanctum'], function () {
        Route::prefix('auth')->group(function () {
            Route::post('logout', 'logout');
            Route::post('complete-profile', 'completeProfile');
            Route::delete('delete-account', 'deleteAccount');
            Route::post('push-notification-on-off', 'pushNotificationOnOff');
        });
    });
});



Route::group(['middleware' => 'auth:sanctum'], function () {

    Route::controller(GeneralController::class)->group(function () {
        Route::prefix('general')->group(function () {
            Route::prefix('notifications')->group(function () {
                Route::get('list', 'notificationList');
                Route::delete('delete', 'notificationDelete');
                Route::get('unread-count', 'notificationUnreadCount');
            });
            Route::get('interest-list', 'interestList');
            Route::post('help-and-feedback', 'helpAndFeedback');

            Route::prefix('favourite')->group(function () {
                Route::get('list', 'favouriteList');
                Route::post('add-remove', 'addRemoveToFavourite');
            });

            Route::prefix('review')->group(function () {
                Route::get('list', 'reviewList');
                Route::post('add', 'addReview');
            });

            Route::prefix('card')->group(function () {
                Route::get('list', 'getCards');
                Route::post('add', 'addCard');
                Route::post('set-as-default', 'makeCardDefault');
                Route::delete('delete', 'deleteCard');
            });
        });
    });

    Route::controller(SocialMediaController::class)->group(function () {
        Route::prefix('post')->group(function () {
            Route::get('list', 'listPost');
            Route::get('detail', 'detailPost');
            Route::post('create', 'createPost');
            Route::post('edit', 'editPost');
            Route::delete('delete', 'deletePost');
            Route::get('view', 'postView');

            Route::post('create-comment', 'postCreateComment');
            Route::delete('delete-comment', 'postDeleteComment');
            Route::post('update-comment', 'postUpdateComment');
            Route::post('create-like-unlike', 'postCreateLikeUnlike');
        });
    });


    Route::controller(UserController::class)->group(function () {
        Route::prefix('user')->group(function () {
            Route::get('profile', 'profile');
            Route::post('availability', 'availability');

            Route::get('home-list', 'homeList');
            Route::get('home-list-all', 'homeListAll');
            Route::get('search', 'search');

            Route::post('user-interest', 'userInterest');
        });

        Route::prefix('follow')->group(function () {
            Route::post('create', 'followCreate');
            Route::get('following', 'following');
            Route::get('followers', 'followers');
        });
        
        Route::post('create-subscription', 'createSubscription');
    });

    Route::controller(AppointmentController::class)->group(function () {
        Route::prefix('appointment')->group(function () {
            Route::get('list', 'myAppointment');
            Route::get('detail', 'detailAppointment');
            Route::get('available-slots', 'availableSlots');
            Route::post('book', 'bookAppointment');
            Route::post('cancel', 'cancelAppointment');
        });
    });


    Route::controller(RemoteMediaController::class)->group(function () {
        Route::post('remote-media/start-conference-call', 'start_conference_call');
        Route::get('remote-media/end-conference-call', 'end_conference_call');
    });
});
