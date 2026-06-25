<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Support\Facades\Route;

// REST API (PRD §5.1 "API & integrations"). Authenticate with a Sanctum bearer
// token issued from Profile → API tokens.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [ApiController::class, 'me']);
    Route::get('/appointments', [ApiController::class, 'appointments']);
    Route::get('/appointments/{appointment}', [ApiController::class, 'show']);
    Route::get('/providers', [ApiController::class, 'providers']);
    Route::get('/services', [ApiController::class, 'services']);
    Route::get('/slots', [ApiController::class, 'slots']);
    Route::post('/appointments', [ApiController::class, 'book']);
});
