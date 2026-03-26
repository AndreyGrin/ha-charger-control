<?php

use App\Http\Controllers\DashboardActionController;
use App\Http\Controllers\DashboardCommandController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeAssistantWebhookController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::post('/actions/{action}', DashboardActionController::class)
    ->whereIn('action', ['plan', 'execute', 'reset', 'stop'])
    ->name('dashboard.action');
Route::post('/artisan-run', DashboardCommandController::class)->name('dashboard.artisan');
Route::post('/ha/webhook/{secret}/connection', [HomeAssistantWebhookController::class, 'connection'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('ha.webhook.connection');
