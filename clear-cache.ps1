# Script PowerShell para limpiar caché de Laravel
Write-Host "🧹 Limpiando caché de Laravel..." -ForegroundColor Yellow

# Limpiar caché de rutas
php artisan route:clear
Write-Host "✅ Caché de rutas limpiado" -ForegroundColor Green

# Limpiar caché de configuración
php artisan config:clear
Write-Host "✅ Caché de configuración limpiado" -ForegroundColor Green

# Limpiar caché de vistas
php artisan view:clear
Write-Host "✅ Caché de vistas limpiado" -ForegroundColor Green

# Limpiar caché de aplicación
php artisan cache:clear
Write-Host "✅ Caché de aplicación limpiado" -ForegroundColor Green

# Recrear caché de rutas (opcional - descomenta si necesitas)
# php artisan route:cache
# Write-Host "✅ Caché de rutas recreado" -ForegroundColor Green

Write-Host "🎉 Limpieza completada. Probá las rutas nuevamente." -ForegroundColor Cyan