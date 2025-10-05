<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyNotificationSetting extends Model
{
    use HasFactory;

    protected $connection = 'notifications';
    
    protected $fillable = [
        'company_subdomain',
        'notification_group_id',
        'template_name',
        'is_active',
        'additional_settings'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'additional_settings' => 'array'
    ];

    /**
     * Grupo de notificación
     */
    public function notificationGroup()
    {
        return $this->belongsTo(NotificationGroup::class, 'notification_group_id');
    }

    /**
     * Notificaciones de facturas enviadas
     */
    public function invoiceNotifications()
    {
        return $this->hasMany(InvoiceNotification::class, 'notification_group_id', 'notification_group_id')
                    ->where('company_subdomain', $this->company_subdomain);
    }

    /**
     * Obtener configuración de empresa
     */
    public static function getCompanySettings(string $companySubdomain): ?self
    {
        return self::with('notificationGroup.contacts')
                   ->where('company_subdomain', $companySubdomain)
                   ->where('is_active', true)
                   ->first();
    }

    /**
     * Obtener todas las empresas configuradas
     */
    public static function getConfiguredCompanies()
    {
        return self::with('notificationGroup.contacts')
                   ->where('is_active', true)
                   ->get();
    }

    /**
     * Verificar si empresa tiene configuración
     */
    public static function hasConfiguration(string $companySubdomain): bool
    {
        return self::where('company_subdomain', $companySubdomain)
                   ->where('is_active', true)
                   ->exists();
    }

    /**
     * Obtener empresas por grupo de notificación
     */
    public static function getCompaniesByGroup(int $groupId): array
    {
        return self::where('notification_group_id', $groupId)
                   ->where('is_active', true)
                   ->pluck('company_subdomain')
                   ->toArray();
    }
}