<?php

use App\Modules\Calendar\Http\CalendarController;
use App\Modules\Calendar\Http\CalendarNotificationController;
use App\Modules\Documents\Http\DocumentController;
use App\Modules\Home\Http\HomeController;
use App\Modules\Kitchen\Http\KitchenActivityController;
use App\Modules\Kitchen\Http\MealController;
use App\Modules\Kitchen\Http\RecipeController;
use App\Modules\Memory\Http\MomentController;
use App\Platform\Families\FamilyController;
use App\Platform\Families\InvitationController;
use App\Platform\Families\TenantContext;
use App\Platform\Identity\PrivateResponse;
use App\Platform\Identity\SessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(PrivateResponse::class)->group(function () {
    Route::get('/session', [SessionController::class, 'show']);
    Route::post('/login', [SessionController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/logout', [SessionController::class, 'logout'])->middleware('auth');
    Route::post('/invitations/{family}/inspect', [InvitationController::class, 'inspect'])->whereUuid('family')->middleware('throttle:30,1');
    Route::post('/invitations/{family}/accept', [InvitationController::class, 'accept'])->whereUuid('family')->middleware('throttle:6,1');
    Route::prefix('/families/{family}')->middleware(['auth', TenantContext::class])->group(function () {
        Route::get('/moments', [MomentController::class, 'index']);
        Route::post('/moments', [MomentController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/moments/{moment}', [MomentController::class, 'show'])->whereUuid('moment');
        Route::patch('/moments/{moment}', [MomentController::class, 'update'])->whereUuid('moment')->middleware('throttle:20,1');
        Route::post('/moments/{moment}/archive', [MomentController::class, 'archive'])->whereUuid('moment')->middleware('throttle:20,1');
        Route::post('/moments/{moment}/restore', [MomentController::class, 'restore'])->whereUuid('moment')->middleware('throttle:20,1');
        Route::get('/moments/{moment}/photo', [MomentController::class, 'photo'])->whereUuid('moment');
        Route::post('/moments/{moment}/photo/scan', [MomentController::class, 'retryPhoto'])->whereUuid('moment')->middleware('throttle:10,1');
        Route::get('/home', HomeController::class);
        Route::get('/kitchen-activity', KitchenActivityController::class);
        Route::get('/meals', [MealController::class, 'index']);
        Route::post('/meals', [MealController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/meals/{meal}', [MealController::class, 'show'])->whereUuid('meal');
        Route::get('/meals/{meal}/photo', [MealController::class, 'photo'])->whereUuid('meal');
        Route::get('/meals/{meal}/recipe', [MealController::class, 'recipe'])->whereUuid('meal');
        Route::put('/meals/{meal}/vote', [MealController::class, 'vote'])->whereUuid('meal')->middleware('throttle:30,1');
        Route::delete('/meals/{meal}/vote', [MealController::class, 'withdraw'])->whereUuid('meal')->middleware('throttle:30,1');
        Route::get('/recipes', [RecipeController::class, 'index']);
        Route::post('/recipes', [RecipeController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/recipes/{recipe}', [RecipeController::class, 'show'])->whereUuid('recipe');
        Route::put('/recipes/{recipe}/sharing', [RecipeController::class, 'sharing'])->whereUuid('recipe')->middleware('throttle:20,1');
        Route::post('/recipes/{recipe}/archive', [RecipeController::class, 'archive'])->whereUuid('recipe')->middleware('throttle:20,1');
        Route::post('/recipes/{recipe}/restore', [RecipeController::class, 'restore'])->whereUuid('recipe')->middleware('throttle:20,1');
        Route::get('/recipes/{recipe}/versions', [RecipeController::class, 'history'])->whereUuid('recipe');
        Route::post('/recipes/{recipe}/versions', [RecipeController::class, 'revise'])->whereUuid('recipe')->middleware('throttle:20,1');
        Route::get('/recipes/{recipe}/versions/{version}', [RecipeController::class, 'version'])->whereUuid(['recipe', 'version']);
        Route::get('/recipes/{recipe}/versions/{version}/photo', [RecipeController::class, 'photo'])->whereUuid(['recipe', 'version']);
        Route::get('/recipes/{recipe}/photo', [RecipeController::class, 'photo'])->whereUuid('recipe');
        Route::post('/recipes/{recipe}/photo/scan', [RecipeController::class, 'retryPhoto'])->whereUuid('recipe')->middleware('throttle:10,1');
        Route::get('/calendar-notifications', [CalendarNotificationController::class, 'index']);
        Route::patch('/calendar-notifications/{notification}', [CalendarNotificationController::class, 'read'])->whereUuid('notification');
        Route::get('/calendar-items', [CalendarController::class, 'index']);
        Route::post('/calendar-items', [CalendarController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/calendar-items/{event}', [CalendarController::class, 'show'])->whereUuid('event');
        Route::patch('/calendar-items/{event}', [CalendarController::class, 'update'])->whereUuid('event');
        Route::post('/calendar-items/{event}/archive', [CalendarController::class, 'archive'])->whereUuid('event');
        Route::post('/calendar-items/{event}/restore', [CalendarController::class, 'restore'])->whereUuid('event');
        Route::get('/members', [FamilyController::class, 'members']);
        Route::patch('/members/{member}/role', [FamilyController::class, 'updateRole'])->whereUuid('member')->middleware('throttle:20,1');
        Route::get('/invitations', [InvitationController::class, 'index']);
        Route::post('/invitations', [InvitationController::class, 'store'])->middleware('throttle:10,1');
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'cancel'])->whereUuid('invitation');
        Route::delete('/members/{member}', [FamilyController::class, 'revoke'])->whereUuid('member');
        Route::get('/documents', [DocumentController::class, 'index']);
        Route::post('/documents', [DocumentController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/documents/{document}', [DocumentController::class, 'show']);
        Route::put('/documents/{document}/sharing', [DocumentController::class, 'sharing']);
        Route::get('/documents/{document}/content', [DocumentController::class, 'download']);
        Route::post('/documents/{document}/scan', [DocumentController::class, 'retry'])->middleware('throttle:10,1');
    });
});

Route::get('/', fn () => redirect('/app/'));
Route::get('/preview/mobile', function () {
    abort_unless(app()->environment('local'), 404);

    return response()->view('mobile-preview', ['appHtml' => file_get_contents(public_path('app/index.html'))])
        ->header('Cache-Control', 'no-store');
});
Route::get('/app/{path?}', function () {
    abort_unless(is_file(public_path('app/index.html')), 503, 'Rulează buildul frontend.');

    return response()->file(public_path('app/index.html'), [
        'Cache-Control' => 'no-cache',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'",
        'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff',
    ]);
})->where('path', '.*');
