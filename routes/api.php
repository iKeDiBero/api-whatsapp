<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\InvoiceErrorController;

// Endpoint público (sin autenticación)
Route::get('/ping', [WhatsAppController::class, 'ping']);

// Endpoints protegidos con autenticación Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Endpoint original de usuario
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Endpoints de WhatsApp
    Route::get('/whatsapp/status', [WhatsAppController::class, 'status']);
    Route::post('/whatsapp/send', [WhatsAppController::class, 'sendMessage']);
    Route::get('/whatsapp/messages', [WhatsAppController::class, 'getMessages']);
    
    // Endpoints de Facturas con Errores
    Route::prefix('invoices')->group(function () {
       
        // Obtener facturas rechazadas de la semana actual (todas las empresas)
        Route::get('rejected/weekly', [InvoiceErrorController::class, 'getWeeklyRejectedInvoices']);
        
        // Obtener facturas rechazadas por empresa específica
        Route::get('rejected/company/{subdominio}', [InvoiceErrorController::class, 'getRejectedInvoicesByCompany']);
        
        // Obtener resumen de errores agrupados por tipo
        Route::get('errors/summary', [InvoiceErrorController::class, 'getErrorSummary']);
        
        // Probar conectividad de todas las bases de datos
        Route::get('connections/test', [InvoiceErrorController::class, 'testAllConnections']);
    });
});
