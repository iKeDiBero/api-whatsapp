<?php

namespace App\Http\Controllers;

use App\Models\EmpresaConnection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class InvoiceErrorController extends Controller
{
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
}