<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClinicalEncounterController;
use App\Http\Controllers\Api\ClinicalExportController;
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

    // Exportación de la HCE: el patrón Abstract Factory elige, en runtime, la
    // familia completa de serializadores del estándar solicitado.
    Route::get('/exchange-standards', [ClinicalExportController::class, 'standards']);
    Route::post('/clinical-exports', [ClinicalExportController::class, 'store']);

    // Notas de atención: el patrón Builder las arma paso a paso y sólo entrega
    // la nota si cumple el contenido mínimo de la Res. 1995 de 1999.
    Route::get('/encounter-types', [ClinicalEncounterController::class, 'types']);
    Route::get('/clinical-encounters', [ClinicalEncounterController::class, 'index']);
    Route::post('/clinical-encounters', [ClinicalEncounterController::class, 'store']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
