<?php

use App\Http\Controllers\Api\WelfareCaseController;
use Illuminate\Support\Facades\Route;

// Task 1: Sanctum tokens and the /me route are removed. The web application authenticates with server-side
// sessions. These two routes are intentionally public (see config/gsk.php public_routes). Everything else
// added here later must sit behind authentication, and a test fails if it does not.
Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json(['service' => 'gsk-api', 'status' => 'ok']));
    Route::post('/welfare-cases', [WelfareCaseController::class, 'store'])->middleware('throttle:5,1');
});
