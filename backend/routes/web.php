<?php

use App\Modules\Calendar\Http\BirthdayReminderController;
use App\Modules\Calendar\Http\CalendarController;
use App\Modules\Calendar\Http\CalendarNotificationController;
use App\Modules\Documents\Http\DocumentController;
use App\Modules\Expenses\Http\ExpenseController;
use App\Modules\Home\Http\HomeController;
use App\Modules\Kitchen\Http\KitchenActivityController;
use App\Modules\Kitchen\Http\MealController;
use App\Modules\Kitchen\Http\RecipeController;
use App\Modules\Memory\Http\MomentController;
use App\Modules\Pets\Http\PetController;
use App\Modules\Subscriptions\Http\SubscriptionController;
use App\Modules\Tasks\Http\TaskController;
use App\Modules\Vehicles\Http\VehicleController;
use App\Platform\Administration\AdminContext;
use App\Platform\Administration\AdminOverviewController;
use App\Platform\Administration\EmailSettingsController;
use App\Platform\Families\FamilyController;
use App\Platform\Families\FamilyGenealogyController;
use App\Platform\Families\InvitationCodeController;
use App\Platform\Families\InvitationController;
use App\Platform\Families\MemberProfileController;
use App\Platform\Families\TenantContext;
use App\Platform\Identity\AccountController;
use App\Platform\Identity\NotificationSettingsController;
use App\Platform\Identity\PrivateResponse;
use App\Platform\Identity\RegistrationController;
use App\Platform\Identity\SessionController;
use App\Platform\Search\FamilySearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(PrivateResponse::class)->group(function () {
    Route::get('/session', [SessionController::class, 'show']);
    Route::post('/login', [SessionController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/register', RegistrationController::class)->middleware('throttle:6,1');
    Route::post('/logout', [SessionController::class, 'logout'])->middleware('auth');
    Route::patch('/account', [AccountController::class, 'update'])->middleware(['auth', 'throttle:20,1']);
    Route::get('/account/notification-settings', [NotificationSettingsController::class, 'show'])->middleware('auth');
    Route::put('/account/notification-settings', [NotificationSettingsController::class, 'update'])->middleware(['auth', 'throttle:20,1']);
    Route::prefix('/admin')->middleware(['auth', AdminContext::class])->group(function () {
        Route::get('/overview', AdminOverviewController::class);
        Route::get('/email-settings', [EmailSettingsController::class, 'show']);
        Route::put('/email-settings', [EmailSettingsController::class, 'update'])->middleware('throttle:20,1');
        Route::post('/email-test', [EmailSettingsController::class, 'test'])->middleware('throttle:admin-email-test');
    });
    Route::post('/invitations/{family}/inspect', [InvitationController::class, 'inspect'])->whereUuid('family')->middleware('throttle:30,1');
    Route::post('/invitations/{family}/accept', [InvitationController::class, 'accept'])->whereUuid('family')->middleware('throttle:6,1');
    Route::prefix('/families/{family}')->middleware(['auth', TenantContext::class])->group(function () {
        Route::get('/search', FamilySearchController::class)->middleware('throttle:90,1');
        Route::get('/subscriptions', [SubscriptionController::class, 'index']);
        Route::post('/subscriptions', [SubscriptionController::class, 'store'])->middleware('throttle:30,1');
        Route::prefix('/subscriptions/{subscription}')->whereUuid('subscription')->group(function () {
            Route::get('/', [SubscriptionController::class, 'show']);
            Route::patch('/', [SubscriptionController::class, 'update'])->middleware('throttle:30,1');
            Route::delete('/', [SubscriptionController::class, 'destroy'])->middleware('throttle:30,1');
            Route::get('/credentials', [SubscriptionController::class, 'credentials'])->middleware('throttle:30,1');
            Route::get('/shares', [SubscriptionController::class, 'shares']);
            Route::post('/shares', [SubscriptionController::class, 'share'])->middleware('throttle:20,1');
            Route::delete('/shares/{share}', [SubscriptionController::class, 'revoke'])->whereUuid('share')->middleware('throttle:20,1');
            Route::post('/accept', [SubscriptionController::class, 'accept'])->middleware('throttle:20,1');
            Route::post('/decline', [SubscriptionController::class, 'decline'])->middleware('throttle:20,1');
        });
        Route::get('/tasks', [TaskController::class, 'index']);
        Route::post('/tasks', [TaskController::class, 'store'])->middleware('throttle:60,1');
        Route::get('/tasks/{task}', [TaskController::class, 'show'])->whereUuid('task');
        Route::patch('/tasks/{task}', [TaskController::class, 'update'])->whereUuid('task')->middleware('throttle:60,1');
        Route::patch('/tasks/{task}/completion', [TaskController::class, 'completion'])->whereUuid('task')->middleware('throttle:60,1');
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->whereUuid('task')->middleware('throttle:60,1');
        Route::get('/expenses', [ExpenseController::class, 'index']);
        Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('throttle:60,1');
        Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->whereUuid('expense');
        Route::patch('/expenses/{expense}', [ExpenseController::class, 'update'])->whereUuid('expense')->middleware('throttle:60,1');
        Route::patch('/expenses/{expense}/payment', [ExpenseController::class, 'payment'])->whereUuid('expense')->middleware('throttle:60,1');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->whereUuid('expense')->middleware('throttle:60,1');
        Route::get('/invitation-codes', [InvitationCodeController::class, 'index']);
        Route::post('/invitation-codes', [InvitationCodeController::class, 'store'])->middleware('throttle:20,1');
        Route::delete('/invitation-codes/{invitationCode}', [InvitationCodeController::class, 'revoke'])->whereUuid('invitationCode');
        Route::get('/vehicles', [VehicleController::class, 'index']);
        Route::get('/pets', [PetController::class, 'index']);
        Route::post('/pets', [PetController::class, 'store'])->middleware('throttle:20,1');
        Route::prefix('/pets/{pet}')->whereUuid('pet')->group(function () {
            Route::get('/', [PetController::class, 'show']);
            Route::patch('/', [PetController::class, 'update'])->middleware('throttle:60,1');
            Route::post('/archive', [PetController::class, 'archive'])->middleware('throttle:60,1');
            Route::get('/photo', [PetController::class, 'photo']);
            Route::post('/photo', [PetController::class, 'uploadPhoto'])->middleware('throttle:20,1');
            Route::post('/photo/retry', [PetController::class, 'retryPhoto'])->middleware('throttle:10,1');
            Route::get('/entries', [PetController::class, 'entries']);
            Route::post('/entries', [PetController::class, 'storeEntry'])->middleware('throttle:60,1');
            Route::get('/entries/{entry}', [PetController::class, 'showEntry'])->whereUuid('entry');
            Route::patch('/entries/{entry}', [PetController::class, 'updateEntry'])->whereUuid('entry')->middleware('throttle:60,1');
            Route::post('/entries/{entry}/archive', [PetController::class, 'archiveEntry'])->whereUuid('entry')->middleware('throttle:60,1');
            Route::patch('/entries/{entry}/completion', [PetController::class, 'completeEntry'])->whereUuid('entry')->middleware('throttle:60,1');
            Route::get('/documents', [PetController::class, 'documents']);
            Route::post('/documents', [PetController::class, 'linkDocument'])->middleware('throttle:60,1');
            Route::delete('/documents/{document}', [PetController::class, 'unlinkDocument'])->whereUuid('document')->middleware('throttle:60,1');
        });
        Route::post('/vehicles', [VehicleController::class, 'store'])->middleware('throttle:20,1');
        Route::prefix('/vehicles/{vehicle}')->whereUuid('vehicle')->group(function () {
            Route::get('/', [VehicleController::class, 'show']);
            Route::patch('/', [VehicleController::class, 'update']);
            Route::post('/archive', [VehicleController::class, 'archive']);
            Route::get('/photo', [VehicleController::class, 'photo']);
            Route::post('/photo', [VehicleController::class, 'uploadPhoto'])->middleware('throttle:20,1');
            Route::post('/photo/scan', [VehicleController::class, 'retryPhoto'])->middleware('throttle:10,1');
            Route::get('/entries', [VehicleController::class, 'entries']);
            Route::post('/entries', [VehicleController::class, 'storeEntry'])->middleware('throttle:20,1');
            Route::get('/entries/{entry}', [VehicleController::class, 'showEntry'])->whereUuid('entry');
            Route::patch('/entries/{entry}', [VehicleController::class, 'updateEntry'])->whereUuid('entry');
            Route::post('/entries/{entry}/archive', [VehicleController::class, 'archiveEntry'])->whereUuid('entry');
            Route::get('/documents', [VehicleController::class, 'documents']);
            Route::post('/documents', [VehicleController::class, 'linkDocument']);
            Route::delete('/documents/{document}', [VehicleController::class, 'unlinkDocument'])->whereUuid('document');
        });
        Route::get('/moments', [MomentController::class, 'index']);
        Route::post('/moments', [MomentController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/moments/{moment}', [MomentController::class, 'show'])->whereUuid('moment');
        Route::patch('/moments/{moment}', [MomentController::class, 'update'])->whereUuid('moment')->middleware('throttle:20,1');
        Route::post('/moments/{moment}/archive', [MomentController::class, 'archive'])->whereUuid('moment')->middleware('throttle:20,1');
        Route::post('/moments/{moment}/restore', [MomentController::class, 'restore'])->whereUuid('moment')->middleware('throttle:20,1');
        Route::get('/moments/{moment}/photo', [MomentController::class, 'photo'])->whereUuid('moment');
        Route::post('/moments/{moment}/photo/scan', [MomentController::class, 'retryPhoto'])->whereUuid('moment')->middleware('throttle:10,1');
        Route::get('/moments/{moment}/photos/{photo}', [MomentController::class, 'photo'])->whereUuid(['moment', 'photo']);
        Route::post('/moments/{moment}/photos/{photo}/scan', [MomentController::class, 'retryPhoto'])->whereUuid(['moment', 'photo'])->middleware('throttle:10,1');
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
        Route::patch('/calendar-items/{event}/birthday-reminder', BirthdayReminderController::class)->whereUuid('event');
        Route::post('/calendar-items', [CalendarController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/calendar-items/{event}', [CalendarController::class, 'show'])->whereUuid('event');
        Route::patch('/calendar-items/{event}', [CalendarController::class, 'update'])->whereUuid('event');
        Route::post('/calendar-items/{event}/archive', [CalendarController::class, 'archive'])->whereUuid('event');
        Route::post('/calendar-items/{event}/restore', [CalendarController::class, 'restore'])->whereUuid('event');
        Route::get('/members', [FamilyController::class, 'members']);
        Route::post('/members/children', [FamilyController::class, 'storeChild'])->middleware('throttle:10,1');
        Route::prefix('/members/{member}/profile')->whereUuid('member')->group(function () {
            Route::get('/', [MemberProfileController::class, 'show']);
            Route::patch('/', [MemberProfileController::class, 'configure'])->middleware('throttle:20,1');
            Route::get('/photo', [MemberProfileController::class, 'photo']);
            Route::post('/photo', [MemberProfileController::class, 'uploadPhoto'])->middleware('throttle:20,1');
            Route::post('/photo/scan', [MemberProfileController::class, 'retryPhoto'])->middleware('throttle:10,1');
            Route::get('/entries', [MemberProfileController::class, 'entries']);
            Route::get('/entries/{entry}', [MemberProfileController::class, 'showEntry'])->whereUuid('entry');
            Route::get('/story', [MemberProfileController::class, 'story']);
            Route::get('/relations', [FamilyGenealogyController::class, 'index']);
            Route::post('/relations', [FamilyGenealogyController::class, 'store'])->middleware('throttle:20,1');
            Route::patch('/relations/people/{person}', [FamilyGenealogyController::class, 'rename'])->whereUuid('person')->middleware('throttle:20,1');
            Route::delete('/relations/{relation}', [FamilyGenealogyController::class, 'remove'])->whereUuid('relation')->middleware('throttle:20,1');
            Route::post('/entries', [MemberProfileController::class, 'storeEntry'])->middleware('throttle:20,1');
            Route::patch('/entries/{entry}', [MemberProfileController::class, 'updateEntry'])->whereUuid('entry')->middleware('throttle:20,1');
            Route::post('/entries/{entry}/archive', [MemberProfileController::class, 'archiveEntry'])->whereUuid('entry')->middleware('throttle:20,1');
        });
        Route::patch('/members/{member}/role', [FamilyController::class, 'updateRole'])->whereUuid('member')->middleware('throttle:20,1');
        Route::get('/invitations', [InvitationController::class, 'index']);
        Route::post('/invitations', [InvitationController::class, 'store'])->middleware('throttle:10,1');
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'cancel'])->whereUuid('invitation');
        Route::delete('/members/{member}', [FamilyController::class, 'revoke'])->whereUuid('member');
        Route::get('/documents', [DocumentController::class, 'index']);
        Route::post('/documents', [DocumentController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/documents/{document}', [DocumentController::class, 'show']);
        Route::patch('/documents/{document}', [DocumentController::class, 'update'])->whereUuid('document');
        Route::post('/documents/{document}/file', [DocumentController::class, 'attachFile'])->whereUuid('document')->middleware('throttle:20,1');
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
