FROM php:8.4-cli

# Instalar dependencias necesarias
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl bcmath gd

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Crear carpeta de la app
WORKDIR /var/www/html

# Copiar el código
COPY . .

# Instalar dependencias PHP
RUN composer install --no-interaction --optimize-autoloader

# Exponer puerto del servidor PHP embebido
EXPOSE 8000

# Comando para iniciar la API
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
