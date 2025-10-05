<?php

namespace App\Http\Controllers;

use App\Models\EmpresaConnection;
use App\Models\InvoiceNotification;
use App\Models\CompanyNotificationSetting;
use App\Services\WhatsAppTemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class InvoiceErrorController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppTemplateService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }
    /**
     * Obtener facturas rechazadas de la semana actual de todas las empresas
     */
    public function getWeeklyRejectedInvoices(): JsonResponse
    {
        try {
            // Obtener todas las conexiones activas
            $connections = EmpresaConnection::getActiveConnections();
            
            if ($connections->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron empresas activas'
                ], 404);
            }

            $allRejectedInvoices = [];
            $totalErrors = 0;
            $successfulConnections = 0;
            $failedConnections = 0;

            // Query para obtener facturas con errores de la semana actual
            $weeklyErrorsQuery = "
                SELECT 
                    id,
                    file_name,
                    error_code,
                    response_descrip,
                    reference_date,
                    issue_date,
                    fCrea,
                    type,
                    status_ticket
                FROM tec_send_invoice
                WHERE error_code <> '0'
                  AND estado = 1
                  AND type = 'RF'
                  AND fCrea >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                  AND fCrea < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
                ORDER BY fCrea DESC
            ";

            foreach ($connections as $connection) {
                try {
                    // Crear instancia del modelo para esta empresa
                    $empresaConnection = EmpresaConnection::find($connection->id_empresa);
                    
                    if (!$empresaConnection) {
                        $failedConnections++;
                        continue;
                    }

                    // Ejecutar consulta en la base de datos de la empresa
                    $rejectedInvoices = $empresaConnection->executeQuery($weeklyErrorsQuery);
                    
                    if ($rejectedInvoices !== false && is_array($rejectedInvoices) && count($rejectedInvoices) > 0) {
                        $allRejectedInvoices[] = [
                            'empresa' => [
                                'id' => $connection->id_empresa,
                                'sub_dominio' => $connection->sub_dominio,
                                'db_name' => $connection->db_name
                            ],
                            'facturas_rechazadas' => $rejectedInvoices,
                            'total_rechazadas' => count($rejectedInvoices)
                        ];
                        
                        $totalErrors += count($rejectedInvoices);
                    }
                    
                    $successfulConnections++;
                    
                } catch (\Exception $e) {
                    $failedConnections++;
                    Log::error("Error procesando empresa {$connection->sub_dominio}: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'data' => $allRejectedInvoices,
                'summary' => [
                    'total_empresas_consultadas' => $connections->count(),
                    'conexiones_exitosas' => $successfulConnections,
                    'conexiones_fallidas' => $failedConnections,
                    'empresas_con_errores' => count($allRejectedInvoices),
                    'total_facturas_rechazadas' => $totalErrors
                ],
                'periodo' => 'Semana actual (Lunes a Domingo)',
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo facturas rechazadas: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener facturas rechazadas por empresa específica
     */
    public function getRejectedInvoicesByCompany($subdominio): JsonResponse
    {
        try {
            // Buscar la conexión por subdominio
            $connection = EmpresaConnection::getConnectionBySubdomain($subdominio);
            
            if (!$connection) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa no encontrada: ' . $subdominio
                ], 404);
            }

            // Query para obtener facturas con errores de la semana actual
            $weeklyErrorsQuery = "
                SELECT 
                    id,
                    file_name,
                    error_code,
                    response_descrip,
                    reference_date,
                    issue_date,
                    fCrea,
                    type,
                    status_ticket
                FROM tec_send_invoice
                WHERE error_code <> '0'
                  AND estado = 1
                  AND type = 'RF' 
                  AND fCrea >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                  AND fCrea < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
                ORDER BY fCrea DESC
            ";

            // Crear instancia del modelo
            $empresaConnection = EmpresaConnection::find($connection->id_empresa);
            
            // Ejecutar consulta
            $rejectedInvoices = $empresaConnection->executeQuery($weeklyErrorsQuery);
            
            if ($rejectedInvoices === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error conectando a la base de datos de la empresa'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'empresa' => [
                        'id' => $connection->id_empresa,
                        'sub_dominio' => $connection->sub_dominio,
                        'db_name' => $connection->db_name
                    ],
                    'facturas_rechazadas' => $rejectedInvoices,
                    'total_rechazadas' => is_array($rejectedInvoices) ? count($rejectedInvoices) : 0
                ],
                'periodo' => 'Semana actual (Lunes a Domingo)',
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error("Error obteniendo facturas rechazadas para {$subdominio}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener resumen de errores por tipo
     */
    public function getErrorSummary(): JsonResponse
    {
        try {
            $connections = EmpresaConnection::getActiveConnections();
            
            if ($connections->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron empresas activas'
                ], 404);
            }

            $errorSummary = [];
            $totalEmpresas = 0;

            // Query para obtener resumen de errores con detalles
            $errorSummaryQuery = "
                SELECT 
                    id,
                    file_name,
                    error_code,
                    response_descrip,
                    reference_date,
                    issue_date,
                    fCrea,
                    type,
                    status_ticket
                FROM tec_send_invoice
                WHERE error_code <> '0'
                  AND estado = 1
                  AND type = 'RF'
                  AND fCrea >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                  AND fCrea < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
                ORDER BY error_code, fCrea DESC
            ";

            foreach ($connections as $connection) {
                try {
                    $empresaConnection = EmpresaConnection::find($connection->id_empresa);
                    
                    if (!$empresaConnection) {
                        continue;
                    }

                    $errors = $empresaConnection->executeQuery($errorSummaryQuery);
                    
                    if ($errors !== false && is_array($errors) && count($errors) > 0) {
                        // Agrupar errores por código
                        $groupedErrors = [];
                        foreach ($errors as $error) {
                            $code = $error['error_code'];
                            if (!isset($groupedErrors[$code])) {
                                $groupedErrors[$code] = [
                                    'error_code' => $code,
                                    'total_facturas' => 0,
                                    'response_descrip' => $error['response_descrip'],
                                    'facturas' => []
                                ];
                            }
                            $groupedErrors[$code]['total_facturas']++;
                            $groupedErrors[$code]['facturas'][] = [
                                'id' => $error['id'],
                                'file_name' => $error['file_name'],
                                'reference_date' => $error['reference_date'],
                                'issue_date' => $error['issue_date'], 
                                'fCrea' => $error['fCrea'],
                                'type' => $error['type'],
                                'status_ticket' => $error['status_ticket']
                            ];
                        }
                        
                        $errorSummary[] = [
                            'empresa' => $connection->sub_dominio,
                            'total_errores' => count($errors),
                            'errores_por_codigo' => array_values($groupedErrors)
                        ];
                    }
                    
                    $totalEmpresas++;
                    
                } catch (\Exception $e) {
                    Log::error("Error procesando resumen para {$connection->sub_dominio}: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'data' => $errorSummary,
                'summary' => [
                    'total_empresas_consultadas' => $totalEmpresas,
                    'empresas_con_errores' => count($errorSummary)
                ],
                'periodo' => 'Semana actual (Lunes a Domingo)',
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo resumen de errores: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Probar conectividad de todas las empresas
     */
    public function testAllConnections(): JsonResponse
    {
        try {
            $connections = EmpresaConnection::getActiveConnections();
            $connectionTests = [];
            
            foreach ($connections as $connection) {
                $empresaConnection = EmpresaConnection::find($connection->id_empresa);
                
                $connectionTests[] = [
                    'empresa' => $connection->sub_dominio,
                    'db_name' => $connection->db_name,
                    'db_host' => $connection->db_host,
                    'status' => $empresaConnection ? $empresaConnection->testConnection() : false
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $connectionTests,
                'summary' => [
                    'total_empresas' => count($connectionTests),
                    'conexiones_exitosas' => count(array_filter($connectionTests, fn($t) => $t['status'])),
                    'conexiones_fallidas' => count(array_filter($connectionTests, fn($t) => !$t['status']))
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error probando conexiones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener facturas rechazadas NO notificadas por empresa
     */
    public function getUnnotifiedInvoicesByCompany($subdominio): JsonResponse
    {
        try {
            $connection = EmpresaConnection::getConnectionBySubdomain($subdominio);
            
            if (!$connection) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa no encontrada: ' . $subdominio
                ], 404);
            }

            // Obtener IDs de facturas ya notificadas
            $notifiedIds = InvoiceNotification::getNotifiedInvoiceIds($subdominio);
            
            // Construir exclusión de facturas ya notificadas
            $excludeCondition = '';
            if (!empty($notifiedIds)) {
                $excludeCondition = ' AND id NOT IN (' . implode(',', array_map('intval', $notifiedIds)) . ')';
            }

            $weeklyErrorsQuery = "
                SELECT 
                    id,
                    file_name,
                    error_code,
                    response_descrip,
                    reference_date,
                    issue_date,
                    fCrea,
                    type,
                    status_ticket
                FROM tec_send_invoice
                WHERE error_code <> '0'
                  AND estado = 1
                  AND type = 'RF' 
                  AND fCrea >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                  AND fCrea < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
                  {$excludeCondition}
                ORDER BY fCrea DESC
            ";

            $empresaConnection = EmpresaConnection::find($connection->id_empresa);
            $unnotifiedInvoices = $empresaConnection->executeQuery($weeklyErrorsQuery);
            
            if ($unnotifiedInvoices === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error conectando a la base de datos de la empresa'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'empresa' => [
                        'id' => $connection->id_empresa,
                        'sub_dominio' => $connection->sub_dominio,
                        'db_name' => $connection->db_name
                    ],
                    'facturas_no_notificadas' => $unnotifiedInvoices,
                    'total_no_notificadas' => is_array($unnotifiedInvoices) ? count($unnotifiedInvoices) : 0,
                    'total_ya_notificadas' => count($notifiedIds)
                ],
                'periodo' => 'Semana actual (Lunes a Domingo)',
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error("Error obteniendo facturas no notificadas para {$subdominio}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar notificación por empresa usando plantilla
     */
    public function sendCompanyNotification(Request $request, $subdominio): JsonResponse
    {
        try {
            // 1. Validar configuración de WhatsApp
            $configErrors = $this->whatsappService->validateConfiguration();
            if (!empty($configErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de configuración de WhatsApp',
                    'errors' => $configErrors
                ], 422);
            }

            // 2. Obtener configuración de notificación para la empresa
            $companySettings = CompanyNotificationSetting::getCompanySettings($subdominio);
            
            if (!$companySettings) {
                return response()->json([
                    'success' => false,
                    'message' => "No hay configuración de notificaciones para la empresa: {$subdominio}",
                    'suggestion' => 'Configura la empresa en la tabla company_notification_settings'
                ], 404);
            }

            // 3. Obtener facturas no notificadas
            $response = $this->getUnnotifiedInvoicesByCompany($subdominio);
            $responseData = $response->getData();

            if (!$responseData->success) {
                return $response;
            }

            $unnotifiedInvoices = $responseData->data->facturas_no_notificadas;
            $empresaData = $responseData->data->empresa;

            // 4. Verificar si hay facturas para notificar
            if (empty($unnotifiedInvoices)) {
                return response()->json([
                    'success' => true,
                    'message' => "No hay facturas nuevas para notificar en {$subdominio}",
                    'data' => [
                        'empresa' => $subdominio,
                        'facturas_notificadas' => 0,
                        'ya_notificadas' => $responseData->data->total_ya_notificadas
                    ]
                ]);
            }

            // 5. Obtener números de teléfono del grupo
            $phoneNumbers = $companySettings->notificationGroup->getActivePhoneNumbers();
            
            if (empty($phoneNumbers)) {
                return response()->json([
                    'success' => false,
                    'message' => "No hay contactos activos configurados para {$subdominio}",
                    'suggestion' => "Agrega contactos al grupo '{$companySettings->notificationGroup->group_name}'"
                ], 422);
            }

            // 6. Preparar parámetros para la plantilla
            $templateParams = $this->buildTemplateParameters($subdominio, $unnotifiedInvoices);

            // 7. Enviar plantilla via WhatsApp
            $deliveryResults = $this->whatsappService->sendTemplate(
                $phoneNumbers,
                $companySettings->template_name,
                $templateParams
            );

            // 8. Calcular estadísticas de envío
            $deliveryStats = $this->whatsappService->getDeliveryStats($deliveryResults);

            // 9. Marcar facturas como notificadas si hubo al menos un envío exitoso
            if ($deliveryStats['successful_deliveries'] > 0) {
                InvoiceNotification::markMultipleAsNotified(
                    $subdominio,
                    $unnotifiedInvoices,
                    $companySettings->template_name,
                    $companySettings->notification_group_id,
                    $deliveryResults
                );
            }

            // 10. Log de la operación
            Log::info("Notificación de empresa enviada", [
                'empresa' => $subdominio,
                'template' => $companySettings->template_name,
                'grupo' => $companySettings->notificationGroup->group_name,
                'facturas_notificadas' => count($unnotifiedInvoices),
                'contactos_exitosos' => $deliveryStats['successful_deliveries'],
                'contactos_fallidos' => $deliveryStats['failed_deliveries']
            ]);

            return response()->json([
                'success' => true,
                'message' => "Notificación enviada para {$subdominio}",
                'data' => [
                    'empresa' => $subdominio,
                    'template_usado' => $companySettings->template_name,
                    'grupo_notificacion' => $companySettings->notificationGroup->group_name,
                    'facturas_notificadas' => count($unnotifiedInvoices),
                    'delivery_stats' => $deliveryStats,
                    'delivery_details' => $deliveryResults,
                    'facturas_detalle' => $unnotifiedInvoices
                ],
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error("Error enviando notificación para {$subdominio}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar notificaciones para todas las empresas con configuración
     */
    public function sendAllCompanyNotifications(): JsonResponse
    {
        try {
            // Obtener todas las configuraciones de empresas activas
            $companySettings = CompanyNotificationSetting::getConfiguredCompanies();

            if ($companySettings->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay empresas configuradas para notificaciones',
                    'suggestion' => 'Configura empresas en la tabla company_notification_settings'
                ], 404);
            }

            $results = [];
            $totalNotified = 0;
            $totalCompaniesProcessed = 0;

            foreach ($companySettings as $setting) {
                try {
                    $response = $this->sendCompanyNotification(new Request(), $setting->company_subdomain);
                    $responseData = $response->getData();

                    $facturas = $responseData->data->facturas_notificadas ?? 0;
                    $totalNotified += $facturas;
                    $totalCompaniesProcessed++;

                    $results[] = [
                        'empresa' => $setting->company_subdomain,
                        'grupo' => $setting->notificationGroup->group_name ?? 'N/A',
                        'template' => $setting->template_name,
                        'facturas_notificadas' => $facturas,
                        'success' => $responseData->success,
                        'notification_sent' => $facturas > 0,
                        'message' => $responseData->message ?? null
                    ];

                } catch (\Exception $e) {
                    $results[] = [
                        'empresa' => $setting->company_subdomain,
                        'grupo' => $setting->notificationGroup->group_name ?? 'N/A',
                        'template' => $setting->template_name,
                        'facturas_notificadas' => 0,
                        'success' => false,
                        'error' => $e->getMessage(),
                        'notification_sent' => false
                    ];
                    
                    Log::error("Error procesando empresa {$setting->company_subdomain}: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Proceso de notificaciones masivas completado',
                'data' => [
                    'resultados_por_empresa' => $results,
                    'resumen' => [
                        'empresas_configuradas' => $companySettings->count(),
                        'empresas_procesadas' => $totalCompaniesProcessed,
                        'empresas_con_notificaciones' => collect($results)->where('notification_sent', true)->count(),
                        'total_facturas_notificadas' => $totalNotified
                    ]
                ],
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Error en notificaciones masivas: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener historial de notificaciones
     */
    public function getNotificationHistory(Request $request): JsonResponse
    {
        try {
            $empresa = $request->get('empresa');
            $limit = min($request->get('limit', 50), 200); // Máximo 200 registros

            $notifications = InvoiceNotification::getNotificationHistory($empresa, $limit);

            return response()->json([
                'success' => true,
                'data' => [
                    'notifications' => $notifications,
                    'total_returned' => count($notifications),
                    'filters' => [
                        'empresa' => $empresa,
                        'limit' => $limit
                    ]
                ],
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo historial de notificaciones: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de notificaciones por empresa
     */
    public function getCompanyNotificationStats(Request $request, $subdominio): JsonResponse
    {
        try {
            $days = min($request->get('days', 7), 90); // Máximo 90 días
            
            $stats = InvoiceNotification::getCompanyStats($subdominio, $days);

            return response()->json([
                'success' => true,
                'data' => [
                    'empresa' => $subdominio,
                    'periodo_dias' => $days,
                    'estadisticas' => $stats
                ],
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error("Error obteniendo estadísticas para {$subdominio}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Probar conexión con WhatsApp API
     */
    public function testWhatsAppConnection(): JsonResponse
    {
        try {
            $result = $this->whatsappService->testConnection();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Conexión exitosa con WhatsApp API' : 'Error de conexión',
                'data' => $result,
                'timestamp' => now()->toISOString()
            ], $result['success'] ? 200 : 422);

        } catch (\Exception $e) {
            Log::error('Error probando conexión WhatsApp: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Construir parámetros para la plantilla
     */
    private function buildTemplateParameters(string $companySubdomain, array $invoices): array
    {
        $totalInvoices = count($invoices);
        
        // Agrupar por tipo de error para resumen
        $errorSummary = [];
        foreach ($invoices as $invoice) {
            $errorCode = $invoice['error_code'];
            if (!isset($errorSummary[$errorCode])) {
                $errorSummary[$errorCode] = [
                    'count' => 0,
                    'description' => $invoice['response_descrip'] ?? 'Error desconocido'
                ];
            }
            $errorSummary[$errorCode]['count']++;
        }

        // Obtener el error más frecuente
        $mainError = collect($errorSummary)
            ->sortByDesc('count')
            ->first();

        $mainErrorCode = array_keys($errorSummary)[0] ?? 'UNKNOWN';
        $mainErrorCount = $mainError['count'] ?? 0;
        $mainErrorDesc = $mainError['description'] ?? 'Error desconocido';

        // Parámetros que coinciden con la plantilla aprobada
        return [
            $companySubdomain,                    // {{1}} - Nombre empresa
            $totalInvoices,                       // {{2}} - Total facturas rechazadas
            $mainErrorCode,                       // {{3}} - Código principal de error
            $mainErrorCount,                      // {{4}} - Cantidad del error principal
            now()->format('d/m/Y H:i'),          // {{5}} - Fecha y hora actual
            substr($mainErrorDesc, 0, 50)        // {{6}} - Descripción corta del error
        ];
    }

    /**
     * Obtener resumen consolidado de todas las empresas para soporte
     */
    public function getGlobalInvoicesSummary(): JsonResponse
    {
        try {
            $connections = EmpresaConnection::getActiveConnections();
            
            if ($connections->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron empresas activas'
                ], 404);
            }

            $globalSummary = [
                'total_companies' => 0,
                'total_invoices' => 0,
                'companies_detail' => [],
                'timestamp' => now()->format('d/m/Y H:i')
            ];

            // Query para facturas rechazadas no notificadas
            $invoiceQuery = "
                SELECT 
                    COUNT(*) as total_invoices,
                    GROUP_CONCAT(DISTINCT error_code) as error_codes
                FROM tec_send_invoice t
                WHERE error_code <> '0'
                  AND estado = 1
                  AND type = 'RF'
                  AND fCrea >= CURDATE()
                  AND NOT EXISTS (
                      SELECT 1 FROM sistemat_db_notifications.invoice_notifications 
                      WHERE company_subdomain = ? 
                      AND invoice_id = t.id 
                      AND DATE(created_at) = CURDATE()
                  )
            ";

            foreach ($connections as $connection) {
                try {
                    $empresaConnection = EmpresaConnection::find($connection->id_empresa);
                    
                    if (!$empresaConnection) {
                        continue;
                    }

                    $result = $empresaConnection->executeQuery($invoiceQuery, [$empresaConnection->sub_dominio]);
                    
                    if ($result !== false && is_array($result) && count($result) > 0) {
                        $companyData = $result[0];
                        $totalInvoices = (int)$companyData['total_invoices'];

                        if ($totalInvoices > 0) {
                            $globalSummary['total_companies']++;
                            $globalSummary['total_invoices'] += $totalInvoices;
                            $globalSummary['companies_detail'][] = [
                                'subdomain' => $empresaConnection->sub_dominio,
                                'total_invoices' => $totalInvoices,
                                'error_codes' => $companyData['error_codes'] ?? ''
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("Error al consultar empresa {$connection->id_empresa}: " . $e->getMessage());
                    continue;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $globalSummary
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getGlobalInvoicesSummary: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen global de facturas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar notificación global al equipo de soporte
     */
    public function sendGlobalSupportNotification(): JsonResponse
    {
        try {
            // Obtener resumen global
            $summaryResponse = $this->getGlobalInvoicesSummary();
            $summaryData = $summaryResponse->getData(true);

            if (!$summaryData['success'] || $summaryData['data']['total_companies'] == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay facturas rechazadas para notificar'
                ]);
            }

            $globalData = $summaryData['data'];

            // Buscar configuración para soporte global
            $supportConfig = CompanyNotificationSetting::where('company_subdomain', 'global_support')
                ->where('is_active', true)
                ->with('notificationGroup.contacts')
                ->first();

            if (!$supportConfig) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró configuración de notificaciones para soporte global'
                ], 404);
            }

            $supportGroup = $supportConfig->notificationGroup;
            if (!$supportGroup || $supportGroup->contacts->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron contactos activos para el grupo de soporte'
                ], 404);
            }

            // Preparar detalle de empresas para el mensaje
            $companiesDetail = collect($globalData['companies_detail'])
                ->map(function($company) {
                    return "• " . strtoupper($company['subdomain']) . ": " . $company['total_invoices'] . " facturas";
                })
                ->implode("\n");

            // Parámetros para la plantilla (temporalmente usando hello_world)
            // Cuando se apruebe alerta_soporte_facturas_global, cambiaremos estos parámetros
            $templateName = 'hello_world'; // Temporal para pruebas
            $templateParams = []; // hello_world no requiere parámetros

            // Para la plantilla final (cuando esté aprobada), usar estos parámetros:
            /*
            $templateName = 'alerta_soporte_facturas_global';
            $templateParams = [
                now()->format('d/m/Y'),                           // {{1}} - Fecha
                now()->format('H:i'),                            // {{2}} - Hora
                (string)$globalData['total_companies'],          // {{3}} - Total empresas
                (string)$globalData['total_invoices'],           // {{4}} - Total facturas
                $companiesDetail,                                // {{5}} - Detalle empresas
                'https://admin.sistemat.com'                     // {{6}} - URL sistema
            ];
            */

            $whatsappService = new WhatsAppTemplateService();
            $results = [];
            $successCount = 0;

            // Enviar a todos los contactos del grupo de soporte
            foreach ($supportGroup->contacts as $contact) {
                if (!$contact->is_active) {
                    continue;
                }

                try {
                    $result = $whatsappService->sendTemplate(
                        [$contact->phone_number], // Debe ser array
                        $templateName,
                        $templateParams
                    );

                    // El servicio devuelve un array de resultados
                    $phoneResult = $result[0] ?? null;
                    $isSuccess = $phoneResult && $phoneResult['success'] === true;

                    $results[] = [
                        'contact' => $contact->name,
                        'phone' => $contact->phone_number,
                        'success' => $isSuccess,
                        'message' => $isSuccess ? 'Enviado correctamente' : ($phoneResult['error'] ?? 'Error desconocido')
                    ];

                    if ($isSuccess) {
                        $successCount++;
                    }

                } catch (\Exception $e) {
                    $results[] = [
                        'contact' => $contact->name,
                        'phone' => $contact->phone_number,
                        'success' => false,
                        'message' => $e->getMessage()
                    ];
                }
            }

            // Registrar las notificaciones enviadas
            if ($successCount > 0) {
                $this->markGlobalInvoicesAsNotified($globalData['companies_detail']);
            }

            return response()->json([
                'success' => true,
                'message' => "Notificaciones enviadas al equipo de soporte",
                'summary' => [
                    'total_companies' => $globalData['total_companies'],
                    'total_invoices' => $globalData['total_invoices'],
                    'notifications_sent' => $successCount,
                    'total_contacts' => count($results)
                ],
                'details' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Error en sendGlobalSupportNotification: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar notificaciones globales',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar facturas como notificadas para todas las empresas
     */
    private function markGlobalInvoicesAsNotified(array $companiesData): void
    {
        foreach ($companiesData as $companyData) {
            try {
                $subdomain = $companyData['subdomain'];
                $empresaConnection = EmpresaConnection::getBySubdomain($subdomain);
                
                if (!$empresaConnection) {
                    continue;
                }

                // Obtener IDs de facturas no notificadas
                $invoiceQuery = "
                    SELECT id 
                    FROM tec_send_invoice 
                    WHERE error_code <> '0'
                      AND estado = 1
                      AND type = 'RF'
                      AND fCrea >= CURDATE()
                      AND NOT EXISTS (
                          SELECT 1 FROM sistemat_db_notifications.invoice_notifications 
                          WHERE company_subdomain = ? 
                          AND invoice_id = id 
                          AND DATE(created_at) = CURDATE()
                      )
                ";

                $invoices = $empresaConnection->executeQuery($invoiceQuery, [$subdomain]);

                if ($invoices && is_array($invoices)) {
                    foreach ($invoices as $invoice) {
                        InvoiceNotification::create([
                            'company_subdomain' => $subdomain,
                            'invoice_id' => $invoice['id'],
                            'notification_type' => 'global_support',
                            'sent_at' => now()
                        ]);
                    }
                }

            } catch (\Exception $e) {
                Log::warning("Error al marcar facturas como notificadas para {$subdomain}: " . $e->getMessage());
            }
        }
    }

    /**
     * Prueba directa de WhatsApp al equipo de soporte
     */
    public function testSupportWhatsApp(): JsonResponse
    {
        try {
            // Buscar configuración para soporte global
            $supportConfig = CompanyNotificationSetting::where('company_subdomain', 'global_support')
                ->where('is_active', true)
                ->with('notificationGroup.contacts')
                ->first();

            if (!$supportConfig) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró configuración de notificaciones para soporte global'
                ], 404);
            }

            $supportGroup = $supportConfig->notificationGroup;
            if (!$supportGroup || $supportGroup->contacts->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron contactos activos para el grupo de soporte'
                ], 404);
            }

            $whatsappService = new WhatsAppTemplateService();
            $results = [];
            $successCount = 0;

            // Enviar hello_world a todos los contactos del grupo de soporte
            foreach ($supportGroup->contacts as $contact) {
                if (!$contact->is_active) {
                    continue;
                }

                try {
                    $result = $whatsappService->sendTemplate(
                        [$contact->phone_number], // Debe ser array
                        'hello_world',
                        [] // hello_world no requiere parámetros
                    );

                    // El servicio devuelve un array de resultados
                    $phoneResult = $result[0] ?? null;
                    $isSuccess = $phoneResult && $phoneResult['success'] === true;

                    $results[] = [
                        'contact' => $contact->name,
                        'phone' => $contact->phone_number,
                        'success' => $isSuccess,
                        'message' => $isSuccess ? 'Enviado correctamente' : ($phoneResult['error'] ?? 'Error desconocido'),
                        'whatsapp_id' => $phoneResult['message_id'] ?? null
                    ];

                    if ($isSuccess) {
                        $successCount++;
                    }

                } catch (\Exception $e) {
                    $results[] = [
                        'contact' => $contact->name,
                        'phone' => $contact->phone_number,
                        'success' => false,
                        'message' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Mensajes de prueba enviados al equipo de soporte",
                'summary' => [
                    'template_used' => 'hello_world',
                    'group_name' => $supportGroup->group_name,
                    'messages_sent' => $successCount,
                    'total_contacts' => count($results)
                ],
                'details' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Error en testSupportWhatsApp: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar mensajes de prueba',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Consultar plantillas disponibles en WhatsApp
     */
    public function getAvailableTemplates(): JsonResponse
    {
        try {
            $whatsappService = new WhatsAppTemplateService();
            $result = $whatsappService->getAvailableTemplates();

            if ($result['success']) {
                // Formatear la información de las plantillas para mejor legibilidad
                $formattedTemplates = collect($result['templates'])->map(function($template) {
                    return [
                        'name' => $template['name'],
                        'status' => $template['status'],
                        'language' => $template['language'],
                        'category' => $template['category'],
                        'components' => count($template['components'] ?? [])
                    ];
                });

                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'total_templates' => $result['total'],
                    'templates' => $formattedTemplates
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'details' => $result['details'] ?? null
                ], $result['status_code'] ?? 500);
            }

        } catch (\Exception $e) {
            Log::error('Error en getAvailableTemplates: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar plantillas disponibles',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}