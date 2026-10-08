<?php

use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\PublicProjectController;
use App\Http\Controllers\Api\PublicRegistrationController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UsherController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/workspace', [WorkspaceController::class, 'show']);
    Route::get('/ushers', [UsherController::class, 'index']);
    Route::post('/ushers', [UsherController::class, 'store']);
    Route::get('/ushers/{usher}/photos/{photo}', [UsherController::class, 'photo']);
    Route::post('/ushers/{usher}/photos', [UsherController::class, 'storePhoto']);
    Route::delete('/ushers/{usher}/photos/{photo}', [UsherController::class, 'destroyPhoto']);
    Route::get('/ushers/{usher}', [UsherController::class, 'show']);
    Route::patch('/ushers/{usher}', [UsherController::class, 'update']);
    Route::delete('/ushers/{usher}', [UsherController::class, 'destroy']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
    Route::post('/projects/{project}/invitations', [ProjectController::class, 'invite']);
    Route::post('/projects/{project}/selection', [ProjectController::class, 'select']);
    Route::post('/projects/{project}/ratings', [RatingController::class, 'store']);
    Route::get('/assignments', [AssignmentController::class, 'index']);
    Route::patch('/assignments/{assignment}', [AssignmentController::class, 'update']);
    Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy']);
    Route::get('/reports', [ReportController::class, 'show']);
});

Route::get('/public/photos/{token}/{photo}', [PublicProjectController::class, 'photo']);
Route::post('/public/register/{token}', [PublicRegistrationController::class, 'store']);
Route::get('/public/availability/{token}', [PublicProjectController::class, 'availability']);
Route::post('/public/availability/{token}', [PublicProjectController::class, 'respond']);
Route::get('/public/client/{token}', [PublicProjectController::class, 'team']);
Route::post('/public/client/{token}', [PublicProjectController::class, 'select']);
Route::get('/public/ratings/{token}', [PublicProjectController::class, 'ratings']);
Route::post('/public/ratings/{token}', [PublicProjectController::class, 'rate']);
