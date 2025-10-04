#!/bin/bash

# Script para limpiar caché de Laravel
echo "🧹 Limpiando caché de Laravel..."

# Limpiar caché de rutas
php artisan route:clear
echo "✅ Caché de rutas limpiado"

# Limpiar caché de configuración
php artisan config:clear
echo "✅ Caché de configuración limpiado"

# Limpiar caché de vistas
php artisan view:clear
echo "✅ Caché de vistas limpiado"

# Limpiar caché de aplicación
php artisan cache:clear
echo "✅ Caché de aplicación limpiado"

# Recrear caché de rutas (opcional)
# php artisan route:cache
# echo "✅ Caché de rutas recreado"

echo "🎉 Limpieza completada. Probá las rutas nuevamente."