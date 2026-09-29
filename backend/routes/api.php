<?php

use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserAccessController;
use App\Http\Controllers\Api\PublicAssetController;
use Illuminate\Support\Facades\Route;

// Ruta pública
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::get('/public/assets/{token}', [PublicAssetController::class, 'show'])->middleware('throttle:public-asset-view');
Route::post('/public/assets/{token}/reports', [PublicAssetController::class, 'report'])->middleware('throttle:public-asset-report');

// Rutas protegidas
Route::middleware(['auth:sanctum', 'active'])->group(function () {

    /*
     * ==========================================================
     * AUTENTICACIÓN
     * ==========================================================
     */
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', DashboardController::class);

    Route::get('/users/technicians', [UserAccessController::class, 'technicians']);
    Route::patch('/users/{user}/active', [UserAccessController::class, 'updateActive']);

    /*
     * ==========================================================
     * ACTIVOS
     * ==========================================================
     */
    Route::get('/areas', [AreaController::class, 'index']);
    Route::get('/assets', [AssetController::class, 'index']);
    Route::post('/assets', [AssetController::class, 'store']);

    /*
     * Categorías para filtros de activos.
     * IMPORTANTE: Esta ruta debe estar antes de /assets/{asset}
     * para que "categories" no sea interpretado como un ID de activo.
     */
    Route::get('/assets/categories', [AssetController::class, 'categories']);

    Route::get('/assets/{asset}', [AssetController::class, 'show']);
    Route::patch('/assets/{asset}', [AssetController::class, 'update']);
    Route::post('/assets/{asset}/transfer', [AssetController::class, 'transfer']);
    Route::patch('/assets/{asset}/status', [AssetController::class, 'changeStatus']);
    Route::get('/assets/{asset}/history', [AssetController::class, 'history']);

    /*
     * ==========================================================
     * TICKETS
     * ==========================================================
     */

    // Listado y creación
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);

    /*
     * Estadísticas para el dashboard.
     *
     * IMPORTANTE:
     * Esta ruta debe estar antes de /tickets/{ticket}
     * para que "stats" no sea interpretado como un ticket.
     */
    Route::get('/tickets/stats', [TicketController::class, 'stats']);

    // Detalle de un ticket
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);

    /*
     * ==========================================================
     * GESTIÓN DE ASIGNACIÓN Y CICLO DEL TICKET
     * ==========================================================
     */

    // Técnico toma voluntariamente un servicio disponible
    Route::post('/tickets/{ticket}/claim', [TicketController::class, 'claim']);

    // Técnico desiste del servicio y lo deja nuevamente disponible
    Route::post('/tickets/{ticket}/release', [TicketController::class, 'release']);

    // Ingeniero asigna o reasigna un técnico
    Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign']);

    // Iniciar atención
    Route::post('/tickets/{ticket}/start', [TicketController::class, 'start']);

    // Resolver servicio
    Route::post('/tickets/{ticket}/resolve', [TicketController::class, 'resolve']);

    // Cierre administrativo definitivo
    Route::post('/tickets/{ticket}/close', [TicketController::class, 'close']);
});
