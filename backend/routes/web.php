<?php

use App\Modules\Documents\Http\DocumentController;
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
