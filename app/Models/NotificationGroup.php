<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationGroup extends Model
{
    use HasFactory;

    protected $connection = 'notifications';
    
    protected $fillable = [
        'group_name',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    /**
     * Contactos del grupo
     */
    public function contacts()
    {
        return $this->hasMany(NotificationContact::class, 'group_id')
                    ->where('is_active', true);
    }

    /**
     * Configuraciones de empresas
     */
    public function companySettings()
    {
        return $this->hasMany(CompanyNotificationSetting::class, 'notification_group_id');
    }

    /**
     * Notificaciones enviadas
     */
    public function invoiceNotifications()
    {
        return $this->hasMany(InvoiceNotification::class, 'notification_group_id');
    }

    /**
     * Obtener números de teléfono activos del grupo
     */
    public function getActivePhoneNumbers(): array
    {
        return $this->contacts()
                    ->pluck('phone_number')
                    ->filter()
                    ->toArray();
    }

    /**
     * Obtener información completa de contactos activos
     */
    public function getActiveContactsInfo(): array
    {
        return $this->contacts()
                    ->select('name', 'phone_number', 'role')
                    ->get()
                    ->toArray();
    }

    /**
     * Obtener grupo por nombre
     */
    public static function getByName(string $groupName): ?self
    {
        return self::where('group_name', $groupName)
                   ->where('is_active', true)
                   ->first();
    }

    /**
     * Obtener todos los grupos activos
     */
    public static function getActiveGroups()
    {
        return self::where('is_active', true)
                   ->with('contacts')
                   ->get();
    }
}