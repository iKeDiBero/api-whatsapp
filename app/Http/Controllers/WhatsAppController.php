<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WhatsAppController extends Controller
{
    /**
     * Obtener el estado del servicio WhatsApp
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'message' => 'WhatsApp API funcionando correctamente',
            'status' => 'active',
            'authenticated_user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'timestamp' => now(),
        ]);
    }

    /**
     * Enviar un mensaje de WhatsApp (simulado)
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string|max:1000',
        ]);

        $user = $request->user();

        // Aquí iría la lógica real de envío de WhatsApp
        // Por ahora solo simulamos la respuesta
        
        return response()->json([
            'success' => true,
            'message' => 'Mensaje enviado correctamente',
            'data' => [
                'phone' => $request->phone,
                'message' => $request->message,
                'sent_by' => $user->name,
                'sent_at' => now(),
                'message_id' => 'wa_' . uniqid(),
            ]
        ]);
    }

    /**
     * Obtener historial de mensajes (simulado)
     */
    public function getMessages(Request $request): JsonResponse
    {
        $user = $request->user();

        // Simulamos algunos mensajes de ejemplo
        $messages = [
            [
                'id' => 'wa_1',
                'phone' => '+1234567890',
                'message' => 'Hola, este es un mensaje de prueba',
                'status' => 'delivered',
                'sent_at' => now()->subMinutes(30),
            ],
            [
                'id' => 'wa_2', 
                'phone' => '+0987654321',
                'message' => 'Otro mensaje de ejemplo',
                'status' => 'read',
                'sent_at' => now()->subHours(2),
            ]
        ];

        return response()->json([
            'success' => true,
            'user' => $user->name,
            'messages' => $messages,
            'total' => count($messages),
        ]);
    }

    /**
     * Endpoint público de prueba (sin autenticación)
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'message' => 'WhatsApp API está funcionando',
            'status' => 'ok',
            'timestamp' => now(),
        ]);
    }
}