# Despliegue

## Permisos de `storage` y `bootstrap/cache`

Laravel necesita escribir en `storage/` (logs, sesiones, cache, uploads) y en `bootstrap/cache/`. Si el proceso PHP (www-data, nginx, o el usuario del contenedor) no tiene permisos, verás errores como:

- **UnexpectedValueException**: *The stream or file ".../storage/logs/laravel.log" could not be opened in append mode: Permission denied*

### En servidor Linux (Docker o no)

Desde la raíz del proyecto:

```bash
# Dar propiedad al usuario del servidor (ajusta www-data si tu servidor usa otro usuario)
sudo chown -R www-data:www-data storage bootstrap/cache

# Dar permisos de escritura a carpetas
sudo chmod -R 775 storage bootstrap/cache
```

### En Docker

Asegúrate de que el usuario con el que corre PHP tenga permisos sobre `storage` y `bootstrap/cache`. Por ejemplo en el Dockerfile o al montar volúmenes:

```bash
chmod -R 775 storage bootstrap/cache
```

O en `docker-compose` ejecutar como el usuario correcto (no root) o montar un volumen con permisos adecuados.

### Comprobar

```bash
php artisan config:clear
php artisan cache:clear
# No debe dar error; si escribe en storage/logs, los permisos están bien
```
