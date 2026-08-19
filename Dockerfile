# Dockerfile Laravel (PHP 8.4 + Nginx): vendor → frontend (Vite) → runtime
# ---------------------------------------------------------------------------
# Stage 1: Composer (para vendor y para Ziggy en el build de Vite)
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---------------------------------------------------------------------------
# Stage 2: Frontend (Vite) - genera public/build/manifest.json
# ---------------------------------------------------------------------------
FROM node:20-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
# npm ci falla de forma intermitente con ETXTBSY en el postinstall de esbuild
# (carrera al ejecutar el binario recién escrito sobre overlayfs). Reintentar una
# vez con node_modules limpio evita el fallo transitorio del build.
RUN npm ci --legacy-peer-deps --no-audit --no-fund \
    || (rm -rf node_modules && npm ci --legacy-peer-deps --no-audit --no-fund)
COPY vite.config.js tailwind.config.js postcss.config.js jsconfig.json ./
COPY resources ./resources
COPY public ./public
COPY --from=vendor /app/vendor ./vendor
ENV NODE_ENV=production
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 3: Runtime (PHP-FPM + Nginx + Supervisord)
# ---------------------------------------------------------------------------
FROM php:8.4-fpm

# Instalar dependencias del sistema (PostgreSQL + Nginx + Supervisord)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    nginx \
    supervisor \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configurar PHP
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Aplicación desde vendor y assets compilados desde frontend
COPY --from=vendor /app /app
COPY --from=frontend /app/public/build /app/public/build
WORKDIR /app

# Crear directorios necesarios
RUN mkdir -p bootstrap/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/logs

# Permisos para que PHP-FPM (www-data) pueda escribir logs, cache, sesiones
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# Configurar PHP-FPM para escuchar en TCP (Nginx en el mismo contenedor lo usa)
RUN if [ -f /usr/local/etc/php-fpm.d/www.conf ]; then \
    sed -i 's|^listen\s*=.*|listen = 127.0.0.1:9000|' /usr/local/etc/php-fpm.d/www.conf || true; \
    sed -i 's|^listen.allowed_clients\s*=.*|;listen.allowed_clients =|' /usr/local/etc/php-fpm.d/www.conf || true; \
    fi

# Configuración Nginx para Laravel (recibe HTTP en 80 y pasa a PHP-FPM)
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
RUN sed -i 's/^daemon .*/daemon off;/' /etc/nginx/nginx.conf \
    && ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default 2>/dev/null || true \
    && mkdir -p /var/log/nginx /var/cache/nginx /var/run \
    && chmod -R 755 /var/log/nginx /var/cache/nginx \
    && nginx -t

# Supervisord: mantiene PHP-FPM y Nginx corriendo (el contenedor no se cierra)
COPY docker/supervisord.conf /etc/supervisor/conf.d/app.conf

# Exponer puerto HTTP (Nginx)
EXPOSE 80

# Arranque: preparar directorios, permisos y ejecutar Supervisord (PID 1)
CMD ["sh", "-c", "mkdir -p bootstrap/cache storage/framework/sessions storage/framework/views storage/framework/cache storage/logs storage/app/public 2>/dev/null || true; chown -R www-data:www-data /app/storage /app/bootstrap/cache 2>/dev/null || true; chmod -R 775 /app/storage /app/bootstrap/cache 2>/dev/null || true; php artisan storage:link 2>/dev/null || true; exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf"]

