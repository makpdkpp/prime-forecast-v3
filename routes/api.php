<?php

use App\Http\Controllers\Api\Mcp\AuditEventController;
use App\Http\Controllers\Api\Mcp\AuthContextController;
use App\Http\Controllers\Api\Mcp\ForecastController;
use App\Http\Controllers\Api\Mcp\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('mcp/v1')
    ->middleware(['mcp.service-key', 'throttle:60,1'])
    ->group(function () {
        Route::get('/health', [HealthController::class, 'show'])->name('api.mcp.health');
        Route::post('/audit-events', [AuditEventController::class, 'store'])->name('api.mcp.audit.store');

        Route::middleware(['mcp.audit', 'auth:sanctum'])->group(function () {
            Route::post('/auth/context', [AuthContextController::class, 'show'])->name('api.mcp.auth.context');
            Route::get('/forecast/me', [ForecastController::class, 'me'])->name('api.mcp.forecast.me');
            Route::get('/teams/{teamId}/forecasts', [ForecastController::class, 'team'])->name('api.mcp.forecast.team');
            Route::get('/sales/{salesId}/forecast', [ForecastController::class, 'sales'])->name('api.mcp.forecast.sales');
            Route::get('/forecast/company', [ForecastController::class, 'company'])->name('api.mcp.forecast.company');
        });
    });
