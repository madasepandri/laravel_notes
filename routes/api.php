<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NoteController;

Route::prefix('v1')->group(function () {
    // Auth routes (public)
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Protected routes (requires authentication)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        // Note CRUD endpoints
        Route::apiResource('notes', NoteController::class);
    });
});
