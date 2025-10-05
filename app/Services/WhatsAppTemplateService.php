<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class WhatsAppTemplateService
{
    private $apiUrl;
    private $accessToken;
    private $phoneNumberId;
    private $businessAccountId;

    public function __construct()
    {
        $this->apiUrl = config('services.whatsapp.api_url', 'https://graph.facebook.com/v18.0');
        $this->accessToken = config('services.whatsapp.access_token');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->businessAccountId = config('services.whatsapp.business_account_id');
    }

    /**
     * Enviar plantilla a múltiples contactos
     */
    public function sendTemplate(array $phoneNumbers, string $templateName, array $parameters = []): array
    {
        $results = [];

        // Validar configuración antes de enviar
        $configErrors = $this->validateConfiguration();
        if (!empty($configErrors)) {
            foreach ($phoneNumbers as $phoneNumber) {
                $results[] = [
                    'phone' => $phoneNumber,
                    'success' => false,
                    'error' => 'Configuración inválida: ' . implode(', ', $configErrors),
                    'error_code' => 'CONFIG_ERROR',
                    'status_code' => 0,
                    'timestamp' => now()->toISOString()
                ];
            }
            return $results;
        }

        foreach ($phoneNumbers as $phoneNumber) {
            try {
                // Limpiar y validar número de teléfono
                $cleanPhone = $this->cleanPhoneNumber($phoneNumber);
                
                if (!$cleanPhone) {
                    $results[] = [
                        'phone' => $phoneNumber,
                        'success' => false,
                        'error' => 'Número de teléfono inválido',
                        'error_code' => 'INVALID_PHONE',
                        'status_code' => 0,
                        'timestamp' => now()->toISOString()
                    ];
                    continue;
                }

                $payload = $this->buildTemplatePayload($cleanPhone, $templateName, $parameters);
                
                $response = Http::withToken($this->accessToken)
                    ->timeout(30)
                    ->retry(2, 1000) // 2 reintentos con 1 segundo de espera
                    ->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", $payload);

                if ($response->successful()) {
                    $responseData = $response->json();
                    $results[] = [
                        'phone' => $phoneNumber,
                        'clean_phone' => $cleanPhone,
                        'success' => true,
                        'message_id' => $responseData['messages'][0]['id'] ?? null,
                        'status_code' => $response->status(),
                        'timestamp' => now()->toISOString()
                    ];

                    Log::info("WhatsApp template enviado exitosamente", [
                        'phone' => $cleanPhone,
                        'template' => $templateName,
                        'message_id' => $responseData['messages'][0]['id'] ?? null
                    ]);

                } else {
                    $errorData = $response->json();
                    $errorMessage = $errorData['error']['message'] ?? 'Error desconocido';
                    $errorCode = $errorData['error']['code'] ?? 'UNKNOWN';

                    $results[] = [
                        'phone' => $phoneNumber,
                        'clean_phone' => $cleanPhone,
                        'success' => false,
                        'error' => $errorMessage,
                        'error_code' => $errorCode,
                        'status_code' => $response->status(),
                        'timestamp' => now()->toISOString()
                    ];

                    Log::warning("Error enviando WhatsApp template", [
                        'phone' => $cleanPhone,
                        'template' => $templateName,
                        'error' => $errorMessage,
                        'error_code' => $errorCode
                    ]);
                }

                // Pausa para evitar rate limits (300ms entre mensajes)
                usleep(300000);

            } catch (Exception $e) {
                $results[] = [
                    'phone' => $phoneNumber,
                    'clean_phone' => $cleanPhone ?? null,
                    'success' => false,
                    'error' => $e->getMessage(),
                    'error_code' => 'EXCEPTION',
                    'status_code' => 0,
                    'timestamp' => now()->toISOString()
                ];
                
                Log::error("Excepción enviando WhatsApp template", [
                    'phone' => $phoneNumber,
                    'template' => $templateName,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return $results;
    }

    /**
     * Construir payload para la plantilla
     */
    private function buildTemplatePayload(string $phoneNumber, string $templateName, array $parameters): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneNumber,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => 'es'
                ]
            ]
        ];

        // Agregar parámetros si existen
        if (!empty($parameters)) {
            $payload['template']['components'] = [
                [
                    'type' => 'body',
                    'parameters' => array_map(function($param) {
                        return [
                            'type' => 'text',
                            'text' => (string) $param
                        ];
                    }, $parameters)
                ]
            ];
        }

        return $payload;
    }

    /**
     * Limpiar y validar número de teléfono
     */
    private function cleanPhoneNumber(string $phoneNumber): ?string
    {
        // Remover espacios, guiones y paréntesis
        $clean = preg_replace('/[\s\-\(\)]/', '', $phoneNumber);
        
        // Remover signos + si existen
        $clean = ltrim($clean, '+');
        
        // Validar que solo contenga números
        if (!preg_match('/^\d+$/', $clean)) {
            return null;
        }

        // Validar longitud (entre 10 y 15 dígitos)
        if (strlen($clean) < 10 || strlen($clean) > 15) {
            return null;
        }

        // Si no empieza con código de país, agregar Colombia por defecto
        if (strlen($clean) === 10 && !str_starts_with($clean, '57')) {
            $clean = '57' . $clean;
        }

        return $clean;
    }

    /**
     * Validar que la configuración de WhatsApp esté completa
     */
    public function validateConfiguration(): array
    {
        $errors = [];

        if (empty($this->accessToken)) {
            $errors[] = 'Access token de WhatsApp no configurado (WHATSAPP_ACCESS_TOKEN)';
        }

        if (empty($this->phoneNumberId)) {
            $errors[] = 'Phone Number ID de WhatsApp no configurado (WHATSAPP_PHONE_NUMBER_ID)';
        }

        if (empty($this->apiUrl)) {
            $errors[] = 'URL de API de WhatsApp no configurada';
        }

        return $errors;
    }

    /**
     * Probar conectividad con la API de WhatsApp
     */
    public function testConnection(): array
    {
        try {
            $configErrors = $this->validateConfiguration();
            if (!empty($configErrors)) {
                return [
                    'success' => false,
                    'error' => 'Configuración inválida',
                    'details' => $configErrors
                ];
            }

            // Intentar obtener información del número de teléfono
            $response = Http::withToken($this->accessToken)
                ->timeout(10)
                ->get("{$this->apiUrl}/{$this->phoneNumberId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Conexión exitosa con WhatsApp API',
                    'phone_info' => $response->json()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error de conexión con WhatsApp API',
                    'status_code' => $response->status(),
                    'response' => $response->json()
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Excepción al probar conexión',
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener estadísticas de envío de un array de resultados
     */
    public function getDeliveryStats(array $deliveryResults): array
    {
        $total = count($deliveryResults);
        $successful = collect($deliveryResults)->where('success', true)->count();
        $failed = $total - $successful;

        return [
            'total_attempts' => $total,
            'successful_deliveries' => $successful,
            'failed_deliveries' => $failed,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0
        ];
    }

    /**
     * Obtener errores únicos de un array de resultados
     */
    public function getUniqueErrors(array $deliveryResults): array
    {
        $errors = collect($deliveryResults)
            ->where('success', false)
            ->groupBy('error_code')
            ->map(function ($group, $errorCode) {
                return [
                    'error_code' => $errorCode,
                    'error_message' => $group->first()['error'] ?? 'Error desconocido',
                    'count' => $group->count(),
                    'affected_phones' => $group->pluck('phone')->toArray()
                ];
            })
            ->values()
            ->toArray();

        return $errors;
    }

    /**
     * Consultar plantillas disponibles en la cuenta
     */
    public function getAvailableTemplates(): array
    {
        try {
            $configErrors = $this->validateConfiguration();
            if (!empty($configErrors)) {
                return [
                    'success' => false,
                    'error' => 'Configuración inválida',
                    'details' => $configErrors
                ];
            }

            // Consultar plantillas usando el Business Account ID
            $response = Http::withToken($this->accessToken)
                ->timeout(15)
                ->get("https://graph.facebook.com/v18.0/{$this->businessAccountId}/message_templates");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message' => 'Plantillas obtenidas exitosamente',
                    'templates' => $data['data'] ?? [],
                    'total' => count($data['data'] ?? [])
                ];
            } else {
                $errorData = $response->json();
                return [
                    'success' => false,
                    'error' => 'Error al consultar plantillas',
                    'status_code' => $response->status(),
                    'details' => $errorData
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Excepción al consultar plantillas',
                'message' => $e->getMessage()
            ];
        }
    }
}