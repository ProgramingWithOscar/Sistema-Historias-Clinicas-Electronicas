<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceReadingController;
use App\Http\Controllers\Api\PingController;
use App\Http\Controllers\Api\SessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', [PingController::class, 'index']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/sessions', [SessionController::class, 'index']);
    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    // Ingesta IoT: el patrón Factory Method resuelve el dispositivo en runtime.
    Route::get('/devices', [DeviceReadingController::class, 'devices']);
    Route::get('/device-readings', [DeviceReadingController::class, 'index']);
    Route::post('/device-readings', [DeviceReadingController::class, 'store']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
