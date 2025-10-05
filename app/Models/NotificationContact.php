<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationContact extends Model
{
    use HasFactory;

    protected $connection = 'notifications';
    
    protected $fillable = [
        'group_id',
        'name',
        'phone_number',
        'role',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    /**
     * Grupo al que pertenece
     */
    public function group()
    {
        return $this->belongsTo(NotificationGroup::class, 'group_id');
    }

    /**
     * Obtener contactos activos por grupo
     */
    public static function getActiveContactsByGroup(int $groupId): array
    {
        return self::where('group_id', $groupId)
                   ->where('is_active', true)
                   ->select('name', 'phone_number', 'role')
                   ->get()
                   ->toArray();
    }

    /**
     * Verificar si un número de teléfono existe
     */
    public static function phoneExists(string $phoneNumber): bool
    {
        return self::where('phone_number', $phoneNumber)
                   ->where('is_active', true)
                   ->exists();
    }
}