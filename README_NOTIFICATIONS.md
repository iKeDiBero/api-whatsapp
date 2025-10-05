# 📱 Sistema de Notificaciones WhatsApp - API Documentation

## 🚀 Configuración Inicial

### 1. Variables de Entorno (.env)
```env
# Base de datos de notificaciones
NOTIFICATIONS_DB_HOST=127.0.0.1
NOTIFICATIONS_DB_PORT=3306
NOTIFICATIONS_DB_DATABASE=sistemat_db_notifications
NOTIFICATIONS_DB_USERNAME=root
NOTIFICATIONS_DB_PASSWORD=

# WhatsApp Cloud API
WHATSAPP_API_URL=https://graph.facebook.com/v18.0
WHATSAPP_ACCESS_TOKEN=tu_access_token
WHATSAPP_PHONE_NUMBER_ID=tu_phone_number_id
```

### 2. Ejecutar Migraciones y Seeders
```bash
# Crear base de datos
CREATE DATABASE sistemat_db_notifications;

# Ejecutar migraciones en la conexión de notificaciones
php artisan migrate --database=notifications

# Ejecutar seeder para datos de prueba
php artisan db:seed --class=NotificationSystemSeeder
```

## 📊 Endpoints de Consulta (Existentes)

### 1. Facturas Rechazadas de la Semana
```http
GET /api/invoices/rejected/weekly
```

### 2. Facturas Rechazadas por Empresa
```http
GET /api/invoices/rejected/company/{subdominio}
```

### 3. Resumen de Errores
```http
GET /api/invoices/errors/summary
```

### 4. Probar Conexiones de BD
```http
GET /api/invoices/connections/test
```

## 📱 Endpoints de Notificaciones (Nuevos)

### 1. Obtener Facturas NO Notificadas por Empresa
```http
GET /api/invoices/unnotified/{subdominio}

Respuesta:
{
    "success": true,
    "data": {
        "empresa": {
            "id": 1,
            "sub_dominio": "empresa1",
            "db_name": "empresa1_db"
        },
        "facturas_no_notificadas": [
            {
                "id": 1001,
                "file_name": "FV001.xml",
                "error_code": "90",
                "response_descrip": "Número duplicado",
                "fCrea": "2024-10-07 14:30:00"
            }
        ],
        "total_no_notificadas": 1,
        "total_ya_notificadas": 5
    }
}
```

### 2. Enviar Notificación por Empresa
```http
POST /api/invoices/notify/{subdominio}

Respuesta:
{
    "success": true,
    "message": "Notificación enviada para empresa1",
    "data": {
        "empresa": "empresa1",
        "template_usado": "alerta_facturas_empresa",
        "grupo_notificacion": "soporte_tecnico",
        "facturas_notificadas": 3,
        "delivery_stats": {
            "total_attempts": 3,
            "successful_deliveries": 2,
            "failed_deliveries": 1,
            "success_rate": 66.67
        },
        "delivery_details": [
            {
                "phone": "573001234567",
                "success": true,
                "message_id": "wamid.xxx"
            }
        ]
    }
}
```

### 3. Enviar Notificaciones Masivas
```http
POST /api/invoices/notify/all

Respuesta:
{
    "success": true,
    "message": "Proceso de notificaciones masivas completado",
    "data": {
        "resultados_por_empresa": [
            {
                "empresa": "empresa1",
                "grupo": "soporte_tecnico",
                "template": "alerta_facturas_empresa",
                "facturas_notificadas": 3,
                "success": true,
                "notification_sent": true
            }
        ],
        "resumen": {
            "empresas_configuradas": 4,
            "empresas_procesadas": 4,
            "empresas_con_notificaciones": 2,
            "total_facturas_notificadas": 8
        }
    }
}
```

### 4. Historial de Notificaciones
```http
GET /api/invoices/notifications/history?empresa=empresa1&limit=50

Respuesta:
{
    "success": true,
    "data": {
        "notifications": [
            {
                "id": 1,
                "company_subdomain": "empresa1",
                "invoice_file_name": "FV001.xml",
                "error_code": "90",
                "notified_at": "2024-10-07 08:05:32",
                "template_used": "alerta_facturas_empresa",
                "notification_group": {
                    "id": 1,
                    "group_name": "soporte_tecnico"
                },
                "status": "sent"
            }
        ],
        "total_returned": 1
    }
}
```

### 5. Estadísticas de Notificaciones por Empresa
```http
GET /api/invoices/notifications/stats/{subdominio}?days=7

Respuesta:
{
    "success": true,
    "data": {
        "empresa": "empresa1",
        "periodo_dias": 7,
        "estadisticas": {
            "total_notifications": 15,
            "successful_notifications": 12,
            "failed_notifications": 2,
            "partial_notifications": 1,
            "unique_invoices": 15,
            "success_rate": 80.0,
            "period_days": 7
        }
    }
}
```

### 6. Probar Conexión WhatsApp
```http
GET /api/invoices/whatsapp/test

Respuesta:
{
    "success": true,
    "message": "Conexión exitosa con WhatsApp API",
    "data": {
        "success": true,
        "phone_info": {
            "id": "123456789012345",
            "display_phone_number": "+57 300 123 4567"
        }
    }
}
```

## 🏢 Configuración de Empresas

### Tabla: `notification_groups`
```sql
| id | group_name      | description                    | is_active |
|----|-----------------|--------------------------------|-----------|
| 1  | soporte_tecnico | Equipo de soporte técnico      | 1         |
| 2  | contabilidad    | Departamento de contabilidad   | 1         |
| 3  | administracion  | Equipo de administración       | 1         |
```

### Tabla: `notification_contacts`
```sql
| id | group_id | name         | phone_number  | role           | is_active |
|----|----------|--------------|---------------|----------------|-----------|
| 1  | 1        | Juan Pérez   | 573001234567  | Técnico Senior | 1         |
| 2  | 1        | María García | 573007654321  | Analista       | 1         |
| 3  | 2        | Carlos Ruíz  | 573009876543  | Contador       | 1         |
```

### Tabla: `company_notification_settings`
```sql
| id | company_subdomain | notification_group_id | template_name           | is_active |
|----|-------------------|----------------------|-------------------------|-----------|
| 1  | empresa1          | 1                    | alerta_facturas_empresa | 1         |
| 2  | empresa2          | 2                    | alerta_facturas_empresa | 1         |
| 3  | empresa3          | 1                    | alerta_facturas_empresa | 1         |
```

## 📱 Plantilla de WhatsApp Recomendada

**Nombre:** `alerta_facturas_empresa`
**Categoría:** `UTILITY`
**Idioma:** `Español (es)`

```
🚨 *ALERTA DE FACTURACIÓN*

🏢 Empresa: {{1}}
📄 Facturas rechazadas: {{2}}
❌ Error principal: {{3}} ({{4}} facturas)
📅 {{5}}

⚠️ {{6}}

Revisar panel de administración.
```

**Parámetros:**
- {{1}} - Nombre de la empresa
- {{2}} - Total facturas rechazadas
- {{3}} - Código principal de error
- {{4}} - Cantidad del error principal
- {{5}} - Fecha y hora actual
- {{6}} - Descripción corta del error

## 🔄 Flujo de Funcionamiento

1. **Configuración Inicial:**
   - Crear grupos de notificación
   - Agregar contactos a los grupos
   - Configurar empresas con sus grupos

2. **Detección de Facturas:**
   - El sistema consulta facturas rechazadas de la semana
   - Excluye facturas ya notificadas previamente
   - Solo procesa facturas NO notificadas

3. **Envío de Notificaciones:**
   - Obtiene configuración de la empresa
   - Construye parámetros de la plantilla
   - Envía plantilla a números del grupo
   - Marca facturas como notificadas

4. **Prevención de Duplicados:**
   - Registra cada notificación en `invoice_notifications`
   - Futuras ejecuciones excluyen facturas ya notificadas
   - Evita spam de notificaciones repetidas

## ⚙️ Comandos de Artisan (Futuro)

```bash
# Enviar notificaciones para todas las empresas
php artisan invoice:send-notifications

# Enviar para empresa específica
php artisan invoice:send-notifications --company=empresa1

# Ver estadísticas
php artisan invoice:notification-stats

# Limpiar notificaciones antiguas
php artisan invoice:cleanup-notifications --days=30
```

## 🚨 Códigos de Error Comunes

- **CONFIG_ERROR**: Configuración de WhatsApp incompleta
- **INVALID_PHONE**: Número de teléfono inválido
- **NO_CONFIGURATION**: Empresa sin configuración
- **NO_CONTACTS**: Grupo sin contactos activos
- **API_LIMIT**: Límite de WhatsApp API alcanzado

## 🔧 Mantenimiento

### Limpieza de Registros
```sql
-- Eliminar notificaciones antiguas (más de 90 días)
DELETE FROM invoice_notifications 
WHERE notified_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

### Verificar Configuración
```sql
-- Empresas sin configuración
SELECT DISTINCT sub_dominio 
FROM empresa_connections 
WHERE sub_dominio NOT IN (
    SELECT company_subdomain 
    FROM company_notification_settings 
    WHERE is_active = 1
);
```