<?php

use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\ContactController;
use App\Http\Controllers\Web\PublicController;
use App\Http\Controllers\Web\Staff\ApprovalController;
use App\Http\Controllers\Web\Staff\ContactMessageController;
use App\Http\Controllers\Web\Staff\ContentController;
use App\Http\Controllers\Web\Staff\MediaController;
use App\Http\Controllers\Web\StaffController;
use App\Http\Controllers\Web\StaffInvitationController;
use Illuminate\Support\Facades\Route;

// Sign-in, sign-out, password reset, email verification and two-factor routes come from Laravel Fortify
// (config/fortify.php, App\Providers\GskAuthServiceProvider). Staff registration does not exist: invitation only.
// Every public route below is listed in config/gsk.php public_routes, and a test fails if one is added without a decision.

// ---- public site ----
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/programmes', [PublicController::class, 'programmes'])->name('programmes');
Route::get('/programmes/{slug}', [PublicController::class, 'programme'])->name('programmes.show');
Route::get('/news', [PublicController::class, 'news'])->name('news');
Route::get('/news/{slug}', [PublicController::class, 'newsPost'])->name('news.show');
Route::get('/jobs', [PublicController::class, 'jobs'])->name('jobs');
Route::get('/jobs/{slug}', [PublicController::class, 'job'])->name('jobs.show');
Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
Route::get('/sitemap.xml', [PublicController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [PublicController::class, 'robots'])->name('robots');

// ---- signed-in area ----
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/account', [AccountController::class, 'dashboard'])->name('account');
    Route::get('/account/security', [AccountController::class, 'security'])->name('account.security');
    Route::get('/staff', [StaffController::class, 'dashboard'])->name('staff.dashboard');

    Route::prefix('staff')->name('staff.')->group(function () {
        Route::get('/content', [ContentController::class, 'index'])->name('content.index');
        Route::prefix('/content/{type}')->where(['type' => 'pages|posts'])->group(function () {
            Route::get('/create', [ContentController::class, 'create'])->name('content.create');
            Route::post('/', [ContentController::class, 'store'])->name('content.store');
            Route::get('/{id}/edit', [ContentController::class, 'edit'])->whereNumber('id')->name('content.edit');
            Route::post('/{id}', [ContentController::class, 'update'])->whereNumber('id')->name('content.update');
            Route::post('/{id}/submit', [ContentController::class, 'submit'])->whereNumber('id')->name('content.submit');
            Route::post('/{id}/request-approval', [ContentController::class, 'requestApproval'])->whereNumber('id')->name('content.request-approval');
            Route::post('/{id}/publish', [ContentController::class, 'publish'])->whereNumber('id')->name('content.publish');
            Route::post('/{id}/archive', [ContentController::class, 'archive'])->whereNumber('id')->name('content.archive');
            Route::get('/{id}/versions', [ContentController::class, 'versions'])->whereNumber('id')->name('content.versions');
            Route::post('/{id}/versions/{version}/restore', [ContentController::class, 'restore'])->whereNumber(['id', 'version'])->name('content.restore');
            Route::get('/{id}/preview', [ContentController::class, 'preview'])->whereNumber('id')->name('content.preview');
        });
        Route::get('/media', [MediaController::class, 'index'])->name('media.index');
        Route::post('/media', [MediaController::class, 'store'])->name('media.store');
        Route::post('/media/{id}/retire', [MediaController::class, 'retire'])->whereNumber('id')->name('media.retire');
        Route::get('/contact-messages', [ContactMessageController::class, 'index'])->name('contact.index');
        Route::get('/contact-messages/{id}', [ContactMessageController::class, 'show'])->whereNumber('id')->name('contact.show');
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::post('/approvals/{id}/{decision}', [ApprovalController::class, 'decide'])->whereNumber('id')->name('approvals.decide');
    });
});

Route::get('/staff/invitation/{token}', [StaffInvitationController::class, 'show'])
    ->middleware('throttle:20,1')->name('staff.invitation');
Route::post('/staff/invitation/{token}', [StaffInvitationController::class, 'accept'])
    ->middleware('throttle:10,1')->name('staff.invitation.accept');

// Content pages by address (about, privacy, safeguarding ...). MUST stay last. Reserved slugs cannot clash.
Route::get('/{slug}', [PublicController::class, 'page'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('page');
