# WhatsApp API - Laravel Sanctum

> **API de WhatsApp con autenticación compartida usando Laravel Sanctum**

Este proyecto es una API de WhatsApp desarrollada en Laravel que utiliza **autenticación compartida** con el proyecto `actec-admin-manager-api`. Ambas APIs comparten la misma base de datos y sistema de tokens, permitiendo que un usuario autenticado en una API pueda acceder a los recursos de la otra.

## 🔐 Autenticación Compartida

Esta API **NO** tiene su propio sistema de autenticación. En su lugar, utiliza los tokens generados por [`actec-admin-manager-api`](https://github.com/iKeDiBero/actec-admin-manager-api) mediante Laravel Sanctum.

### Cómo funciona:
1. **Login**: El usuario se autentica en `actec-admin-manager-api`
2. **Token**: Obtiene un token de acceso
3. **Acceso**: Usa el mismo token para acceder a los endpoints de esta API

## 🚀 Características

- ✅ **Autenticación compartida** con `actec-admin-manager-api`
- ✅ **Laravel Sanctum** para gestión de tokens
- ✅ **Base de datos compartida** para usuarios y tokens
- ✅ **Endpoints protegidos** para funcionalidades de WhatsApp
- ✅ **Validación de requests** con Laravel Validation

## 📋 Requisitos

- PHP >= 8.1
- Laravel 11.x
- MySQL/MariaDB
- Composer
- **Proyecto `actec-admin-manager-api` configurado y funcionando**

## 🛠️ Instalación

### 1. Clonar el repositorio
```bash
git clone https://github.com/iKeDiBero/whatsapp-api.git
cd whatsapp-api
```

### 2. Instalar dependencias
```bash
composer install
npm install
```

### 3. Configurar variables de entorno
```bash
cp .env.example .env
```

Configura las variables de base de datos **EXACTAMENTE IGUALES** a las de `actec-admin-manager-api`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3305
DB_DATABASE=sistemat_db_wsadministrador
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Generar clave de aplicación
```bash
php artisan key:generate
```

### 5. Levantar el servidor
```bash
php artisan serve --port=8001
```

## 🔗 Endpoints Disponibles

### Endpoints Públicos
- `GET /api/ping` - Verificar estado del servicio

### Endpoints Protegidos (requieren token de actec-admin-manager-api)
- `GET /api/user` - Información del usuario autenticado
- `GET /api/whatsapp/status` - Estado del servicio WhatsApp
- `POST /api/whatsapp/send` - Enviar mensaje de WhatsApp
- `GET /api/whatsapp/messages` - Obtener historial de mensajes

## 🧪 Uso y Ejemplos

### 1. Obtener token de autenticación
```bash
# En actec-admin-manager-api (puerto 8000)
curl -X POST "http://localhost:8000/api/login" \
  -H "Content-Type: application/json" \
  -d '{"email": "usuario@ejemplo.com", "password": "contraseña"}'
```

### 2. Usar el token en WhatsApp API
```bash
# Verificar estado del servicio
curl -X GET "http://localhost:8001/api/whatsapp/status" \
  -H "Authorization: Bearer TU_TOKEN_AQUI"

# Enviar mensaje
curl -X POST "http://localhost:8001/api/whatsapp/send" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TU_TOKEN_AQUI" \
  -d '{"phone": "+1234567890", "message": "Hola desde WhatsApp API"}'
```

## ⚙️ Configuración de Autenticación Compartida

Esta API está configurada para compartir autenticación con `actec-admin-manager-api` mediante:

1. **Base de datos compartida**: Ambas APIs usan la misma base de datos
2. **Tabla de usuarios compartida**: Acceden a la misma tabla `users`
3. **Tokens Sanctum compartidos**: Los tokens se almacenan en `personal_access_tokens`
4. **Middleware Sanctum**: Valida los tokens en ambas APIs

## 🔒 Seguridad

- ✅ Los tokens son validados contra la misma base de datos
- ✅ La revocación de tokens afecta ambas APIs
- ✅ Gestión centralizada de usuarios y permisos
- ✅ Información sensible protegida con `.gitignore`

## 🤝 APIs Relacionadas

- [`actec-admin-manager-api`](https://github.com/iKeDiBero/actec-admin-manager-api) - API principal de administración

## 📄 Licencia

Este proyecto está bajo la licencia MIT.
