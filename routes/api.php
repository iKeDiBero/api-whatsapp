<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppController;

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
});
