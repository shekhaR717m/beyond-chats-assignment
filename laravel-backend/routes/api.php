<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ArticleController;

Route::prefix('articles')->group(function () {
    // List all articles
    Route::get('/', [ArticleController::class, 'index']);

    // Get latest ungenerated article
    Route::get('/latest/ungenerated', [ArticleController::class, 'getLatestUngenerated']);

    // Get single article
    Route::get('/{id}', [ArticleController::class, 'show']);

    // Create article
    Route::post('/', [ArticleController::class, 'store']);

    // Update article
    Route::put('/{id}', [ArticleController::class, 'update']);

    // Delete article
    Route::delete('/{id}', [ArticleController::class, 'destroy']);
});

// Health check
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});
