<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class InvoiceNotification extends Model
{
    use HasFactory;

    protected $connection = 'notifications';
    
    protected $fillable = [
        'company_subdomain',
        'invoice_id',
        'invoice_file_name',
        'error_code',
        'error_description',
        'invoice_date',
        'notified_at',
        'template_used',
        'notification_group_id',
        'delivery_results',
        'status'
    ];

    protected $casts = [
        'invoice_date' => 'datetime',
        'notified_at' => 'datetime',
        'delivery_results' => 'array'
    ];

    /**
     * Grupo de notificación
     */
    public function notificationGroup()
    {
        return $this->belongsTo(NotificationGroup::class, 'notification_group_id');
    }

    /**
     * Configuración de empresa
     */
    public function companySettings()
    {
        return $this->belongsTo(CompanyNotificationSetting::class, 'company_subdomain', 'company_subdomain');
    }

    /**
     * Verificar si una factura ya fue notificada
     */
    public static function isInvoiceNotified(string $companySubdomain, int $invoiceId): bool
    {
        return self::where('company_subdomain', $companySubdomain)
                   ->where('invoice_id', $invoiceId)
                   ->whereIn('status', ['sent', 'partial'])
                   ->exists();
    }

    /**
     * Obtener IDs de facturas ya notificadas por empresa
     */
    public static function getNotifiedInvoiceIds(string $companySubdomain): array
    {
        return self::where('company_subdomain', $companySubdomain)
                   ->whereIn('status', ['sent', 'partial'])
                   ->pluck('invoice_id')
                   ->map(function($id) {
                       return (int) $id;
                   })
                   ->toArray();
    }

    /**
     * Marcar facturas como notificadas
     */
    public static function markAsNotified(
        string $companySubdomain,
        array $invoiceData,
        string $templateUsed,
        int $notificationGroupId,
        array $deliveryResults
    ): self {
        return self::create([
            'company_subdomain' => $companySubdomain,
            'invoice_id' => $invoiceData['id'],
            'invoice_file_name' => $invoiceData['file_name'],
            'error_code' => $invoiceData['error_code'],
            'error_description' => $invoiceData['response_descrip'] ?? null,
            'invoice_date' => $invoiceData['fCrea'],
            'notified_at' => now(),
            'template_used' => $templateUsed,
            'notification_group_id' => $notificationGroupId,
            'delivery_results' => $deliveryResults,
            'status' => self::determineStatus($deliveryResults)
        ]);
    }

    /**
     * Marcar múltiples facturas como notificadas en lote
     */
    public static function markMultipleAsNotified(
        string $companySubdomain,
        array $invoicesData,
        string $templateUsed,
        int $notificationGroupId,
        array $deliveryResults
    ): int {
        $notifications = [];
        $status = self::determineStatus($deliveryResults);
        $notifiedAt = now();

        foreach ($invoicesData as $invoice) {
            $notifications[] = [
                'company_subdomain' => $companySubdomain,
                'invoice_id' => $invoice['id'],
                'invoice_file_name' => $invoice['file_name'],
                'error_code' => $invoice['error_code'],
                'error_description' => $invoice['response_descrip'] ?? null,
                'invoice_date' => $invoice['fCrea'],
                'notified_at' => $notifiedAt,
                'template_used' => $templateUsed,
                'notification_group_id' => $notificationGroupId,
                'delivery_results' => json_encode($deliveryResults),
                'status' => $status,
                'created_at' => $notifiedAt,
                'updated_at' => $notifiedAt
            ];
        }

        return self::insert($notifications);
    }

    /**
     * Determinar estado basado en resultados de entrega
     */
    private static function determineStatus(array $deliveryResults): string
    {
        if (empty($deliveryResults)) {
            return 'failed';
        }

        $successful = collect($deliveryResults)->where('success', true)->count();
        $total = count($deliveryResults);

        if ($successful === 0) return 'failed';
        if ($successful === $total) return 'sent';
        return 'partial';
    }

    /**
     * Obtener estadísticas de notificaciones por empresa
     */
    public static function getCompanyStats(string $companySubdomain, int $days = 7): array
    {
        $since = Carbon::now()->subDays($days);
        
        $stats = self::where('company_subdomain', $companySubdomain)
                     ->where('notified_at', '>=', $since)
                     ->selectRaw('
                         COUNT(*) as total_notifications,
                         COUNT(CASE WHEN status = "sent" THEN 1 END) as successful_notifications,
                         COUNT(CASE WHEN status = "failed" THEN 1 END) as failed_notifications,
                         COUNT(CASE WHEN status = "partial" THEN 1 END) as partial_notifications,
                         COUNT(DISTINCT invoice_id) as unique_invoices
                     ')
                     ->first();

        return [
            'total_notifications' => $stats->total_notifications ?? 0,
            'successful_notifications' => $stats->successful_notifications ?? 0,
            'failed_notifications' => $stats->failed_notifications ?? 0,
            'partial_notifications' => $stats->partial_notifications ?? 0,
            'unique_invoices' => $stats->unique_invoices ?? 0,
            'success_rate' => $stats->total_notifications > 0 
                ? round(($stats->successful_notifications / $stats->total_notifications) * 100, 2) 
                : 0,
            'period_days' => $days
        ];
    }

    /**
     * Obtener historial de notificaciones
     */
    public static function getNotificationHistory(?string $companySubdomain = null, int $limit = 50): array
    {
        $query = self::with('notificationGroup:id,group_name')
                     ->orderBy('notified_at', 'desc');

        if ($companySubdomain) {
            $query->where('company_subdomain', $companySubdomain);
        }

        return $query->limit($limit)
                     ->get()
                     ->toArray();
    }
}