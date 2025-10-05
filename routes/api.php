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
       
        // === ENDPOINTS EXISTENTES (CONSULTA DE DATOS) ===
        
        // Obtener facturas rechazadas de la semana actual (todas las empresas)
        Route::get('rejected/weekly', [InvoiceErrorController::class, 'getWeeklyRejectedInvoices']);
        
        // Obtener facturas rechazadas por empresa específica
        Route::get('rejected/company/{subdominio}', [InvoiceErrorController::class, 'getRejectedInvoicesByCompany']);
        
        // Obtener resumen de errores agrupados por tipo
        Route::get('errors/summary', [InvoiceErrorController::class, 'getErrorSummary']);
        
        // Probar conectividad de todas las bases de datos
        Route::get('connections/test', [InvoiceErrorController::class, 'testAllConnections']);
        
        // === NUEVOS ENDPOINTS (SISTEMA DE NOTIFICACIONES) ===
        
        // Obtener facturas NO notificadas por empresa
        Route::get('unnotified/{subdominio}', [InvoiceErrorController::class, 'getUnnotifiedInvoicesByCompany']);
        
        // Enviar notificación para empresa específica
        Route::post('notify/{subdominio}', [InvoiceErrorController::class, 'sendCompanyNotification']);
        
        // Enviar notificaciones para todas las empresas configuradas
        Route::post('notify/all', [InvoiceErrorController::class, 'sendAllCompanyNotifications']);
        
        // Obtener historial de notificaciones enviadas
        Route::get('notifications/history', [InvoiceErrorController::class, 'getNotificationHistory']);
        
        // Obtener estadísticas de notificaciones por empresa
        Route::get('notifications/stats/{subdominio}', [InvoiceErrorController::class, 'getCompanyNotificationStats']);
        
        // Probar conexión con WhatsApp API
        Route::get('whatsapp/test', [InvoiceErrorController::class, 'testWhatsAppConnection']);
        
        // === ENDPOINTS DE SOPORTE GLOBAL ===
        
        // Obtener resumen consolidado de todas las empresas para soporte
        Route::get('support/global-summary', [InvoiceErrorController::class, 'getGlobalInvoicesSummary']);
        
        // Enviar notificación consolidada al equipo de soporte
        Route::post('support/notify-global', [InvoiceErrorController::class, 'sendGlobalSupportNotification']);
        
        // Prueba directa de WhatsApp al equipo de soporte (hello_world)
        Route::post('support/test-whatsapp', [InvoiceErrorController::class, 'testSupportWhatsApp']);
        
        // Consultar plantillas disponibles en WhatsApp
        Route::get('whatsapp/templates', [InvoiceErrorController::class, 'getAvailableTemplates']);
    });
});
