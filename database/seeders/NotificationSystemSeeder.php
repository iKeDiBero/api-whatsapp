<?php

namespace Database\Seeders;

use App\Models\NotificationGroup;
use App\Models\NotificationContact;
use App\Models\CompanyNotificationSetting;
use Illuminate\Database\Seeder;

class NotificationSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crear grupos de notificación
        $soporteGroup = NotificationGroup::create([
            'group_name' => 'soporte_tecnico',
            'description' => 'Equipo de soporte técnico para facturas rechazadas',
            'is_active' => true
        ]);

        $contabilidadGroup = NotificationGroup::create([
            'group_name' => 'contabilidad',
            'description' => 'Departamento de contabilidad y facturación',
            'is_active' => true
        ]);

        $administracionGroup = NotificationGroup::create([
            'group_name' => 'administracion',
            'description' => 'Equipo de administración y gerencia',
            'is_active' => true
        ]);

        // 2. Agregar contactos al grupo de soporte técnico
        NotificationContact::create([
            'group_id' => $soporteGroup->id,
            'name' => 'Juan Pérez',
            'phone_number' => '573001234567',
            'role' => 'Técnico Senior',
            'is_active' => true
        ]);

        NotificationContact::create([
            'group_id' => $soporteGroup->id,
            'name' => 'María García',
            'phone_number' => '573007654321',
            'role' => 'Analista de Sistemas',
            'is_active' => true
        ]);

        NotificationContact::create([
            'group_id' => $soporteGroup->id,
            'name' => 'Luis Martínez',
            'phone_number' => '573002345678',
            'role' => 'Desarrollador',
            'is_active' => true
        ]);

        // 3. Agregar contactos al grupo de contabilidad
        NotificationContact::create([
            'group_id' => $contabilidadGroup->id,
            'name' => 'Carlos Rodríguez',
            'phone_number' => '573009876543',
            'role' => 'Contador Principal',
            'is_active' => true
        ]);

        NotificationContact::create([
            'group_id' => $contabilidadGroup->id,
            'name' => 'Ana López',
            'phone_number' => '573008765432',
            'role' => 'Auxiliar Contable',
            'is_active' => true
        ]);

        // 4. Agregar contactos al grupo de administración
        NotificationContact::create([
            'group_id' => $administracionGroup->id,
            'name' => 'Roberto Silva',
            'phone_number' => '573005432109',
            'role' => 'Gerente General',
            'is_active' => true
        ]);

        NotificationContact::create([
            'group_id' => $administracionGroup->id,
            'name' => 'Patricia Morales',
            'phone_number' => '573006543210',
            'role' => 'Coordinadora Administrativa',
            'is_active' => true
        ]);

        // 5. Configurar empresas con grupos específicos
        CompanyNotificationSetting::create([
            'company_subdomain' => 'empresa1',
            'notification_group_id' => $soporteGroup->id,
            'template_name' => 'alerta_facturas_empresa',
            'is_active' => true,
            'additional_settings' => [
                'send_daily_summary' => true,
                'urgent_threshold' => 10
            ]
        ]);

        CompanyNotificationSetting::create([
            'company_subdomain' => 'empresa2',
            'notification_group_id' => $contabilidadGroup->id,
            'template_name' => 'alerta_facturas_empresa',
            'is_active' => true,
            'additional_settings' => [
                'send_daily_summary' => false,
                'urgent_threshold' => 5
            ]
        ]);

        CompanyNotificationSetting::create([
            'company_subdomain' => 'empresa3',
            'notification_group_id' => $soporteGroup->id,
            'template_name' => 'alerta_facturas_empresa',
            'is_active' => true,
            'additional_settings' => [
                'send_daily_summary' => true,
                'urgent_threshold' => 15
            ]
        ]);

        CompanyNotificationSetting::create([
            'company_subdomain' => 'empresatest',
            'notification_group_id' => $administracionGroup->id,
            'template_name' => 'alerta_facturas_empresa',
            'is_active' => true,
            'additional_settings' => [
                'send_daily_summary' => true,
                'urgent_threshold' => 3
            ]
        ]);

        // 6. Crear un grupo inactivo para testing
        $inactiveGroup = NotificationGroup::create([
            'group_name' => 'grupo_inactivo',
            'description' => 'Grupo desactivado para pruebas',
            'is_active' => false
        ]);

        NotificationContact::create([
            'group_id' => $inactiveGroup->id,
            'name' => 'Usuario Inactivo',
            'phone_number' => '573000000000',
            'role' => 'Test',
            'is_active' => false
        ]);

        $this->command->info('✅ Seeder ejecutado correctamente:');
        $this->command->info("   📊 Grupos creados: " . NotificationGroup::count());
        $this->command->info("   👥 Contactos creados: " . NotificationContact::count());
        $this->command->info("   🏢 Empresas configuradas: " . CompanyNotificationSetting::count());
        $this->command->info("");
        $this->command->info("🔗 Configuración de grupos:");
        $this->command->info("   • soporte_tecnico: 3 contactos");
        $this->command->info("   • contabilidad: 2 contactos");
        $this->command->info("   • administracion: 2 contactos");
        $this->command->info("");
        $this->command->info("🏢 Empresas configuradas:");
        $this->command->info("   • empresa1 → soporte_tecnico");
        $this->command->info("   • empresa2 → contabilidad");
        $this->command->info("   • empresa3 → soporte_tecnico");
        $this->command->info("   • empresatest → administracion");
    }
}