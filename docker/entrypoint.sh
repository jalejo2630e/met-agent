#!/bin/sh
# No usar set -e: si php-fpm o artisan fallan, igual debemos arrancar nginx para que el contenedor siga vivo

# Iniciar PHP-FPM en segundo plano (necesario para que Nginx sirva PHP)
php-fpm -D 2>/dev/null || true

# Optimizar Laravel solo si la app está configurada (APP_KEY, etc.)
if php artisan config:cache --no-interaction 2>/dev/null; then
    php artisan route:cache --no-interaction 2>/dev/null || true
    php artisan view:cache --no-interaction 2>/dev/null || true
fi

# Migraciones solo si la DB está disponible
php artisan migrate --force --no-interaction 2>/dev/null || true

# Comando principal: Nginx en primer plano (mantiene el contenedor vivo)
if [ $# -gt 0 ]; then
    exec "$@"
else
    exec nginx -g "daemon off;"
fi
