# Despliegue en Dokploy con Dockerfile

Configuración para desplegar **Administrador de Agentes** (Laravel 12 + Vue 3) en [Dokploy](https://dokploy.com) usando el Dockerfile incluido en el repositorio.

## Requisitos previos

- Instancia de Dokploy instalada y funcionando.
- Repositorio Git accesible desde Dokploy (GitHub, GitLab, etc.).
- Base de datos PostgreSQL (puedes crear una **Database** en Dokploy o usar una externa).

---

## 1. Crear la aplicación en Dokploy

1. En el panel de Dokploy: **Applications** → **Create Application**.
2. Conecta el **repositorio** (Git Source) y selecciona la rama a desplegar (p. ej. `main`).

---

## 2. Configurar el tipo de build (Dockerfile)

En la aplicación creada, en **Build** / **Build Type**:

| Campo | Valor |
|--------|--------|
| **Build Type** | `Dockerfile` |
| **Dockerfile Path** | `Dockerfile` |
| **Docker Context Path** | `.` |
| **Docker Build Stage** | (dejar vacío; se usa la etapa final por defecto) |

El Dockerfile está en la raíz del proyecto y usa una única imagen final (runtime).

---

## 3. Variables de entorno

En la pestaña **Environment** / **Variables** define al menos:

```env
APP_NAME="Administrador de Agentes"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com   # Debe ser HTTPS para evitar Mixed Content en el navegador

APP_KEY=base64:...   # Generar con: php artisan key:generate --show

# Base de datos (PostgreSQL en Dokploy o externa)
DB_CONNECTION=pgsql
DB_HOST=tu-servicio-postgres
DB_PORT=5432
DB_DATABASE=administrador_agentes
DB_USERNAME=postgres
DB_PASSWORD=tu_password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# Opcionales
LOG_CHANNEL=stack
LOG_LEVEL=warning
```

- Si usas una **Database** de Dokploy (PostgreSQL), en la misma aplicación añade el **Database** como servicio vinculado y usa el **host** que te indique Dokploy (nombre del servicio).
- `APP_KEY` es obligatorio: puedes generarlo en local con `php artisan key:generate --show` y pegarlo.

**Si sale 502 Bad Gateway:** casi siempre es porque faltan variables de entorno. Comprueba en **Environment** que estén definidas **APP_KEY** (obligatoria) y **DB_HOST**, **DB_DATABASE**, **DB_USERNAME**, **DB_PASSWORD** (y que la base de datos exista y sea accesible). Sin APP_KEY o con la base de datos mal configurada, Laravel no arranca y Nginx devuelve 502.

---

## 4. Dominio y puerto

- En **Domains** añade tu dominio (o el subdominio que te asigne Dokploy).
- La aplicación escucha en el **puerto 80** dentro del contenedor; Dokploy/Traefik se encargan del enrutamiento. No hace falta publicar puertos manualmente si usas dominio.

Si accedes por IP:puerto, en **Advanced** → **Ports** puedes publicar:
- **Published Port**: p. ej. `80` o el que quieras en el host.
- **Target Port**: `80`.

**Mixed Content (HTTPS):** Si la página carga por HTTPS pero los CSS/JS salen por HTTP y el navegador los bloquea: 1) Configura `APP_URL=https://tu-dominio.com` (con https). 2) Tras desplegar, ejecuta `php artisan config:cache`. La app fuerza HTTPS en producción y confía en proxies (TrustProxies).

---

## 5. Volúmenes (opcional)

Para persistir **storage** (logs, cache, sesiones, archivos subidos):

- **Advanced** → **Volumes/Mounts**:
  - Tipo: **Volume Mount**.
  - **Volume Name**: p. ej. `administrador-agentes-storage`.
  - **Mount Path**: `/var/www/html/storage`.

Así no pierdes logs ni archivos entre despliegues.

---

## 6. Desplegar

1. Guarda la configuración.
2. **Deploy** / **Redeploy** para construir la imagen con el Dockerfile y levantar el contenedor.

En el primer arranque el contenedor solo inicia **PHP-FPM** y **Nginx** (sin migraciones ni cache en el arranque). Para migraciones y optimización, en Dokploy usa **Advanced** → **Run Command** después del primer deploy, por ejemplo:

- `php artisan migrate --force`
- `php artisan config:cache && php artisan route:cache && php artisan view:cache`

---

## 7. Cola de trabajos (Queue) y scheduler (opcional)

La app usa colas (`QUEUE_CONNECTION=database`) y posiblemente un scheduler. Opciones:

- **Opción A – Mismo contenedor**: en **Advanced** → **Run Command** o definiendo un **Start Command** que levante además un worker (p. ej. `php artisan queue:work`) y un cron para el scheduler. No recomendado si quieres escalar solo la web.
- **Opción B – Servicio aparte**: crear otra **Application** en Dokploy con la misma imagen y **Start Command**: `php artisan queue:work --sleep=3 --tries=3`, y opcionalmente otra para `schedule:work` o un cron externo.

Por defecto el Dockerfile solo arranca la web (Nginx + PHP-FPM). Añadir workers/scheduler es opcional según tu necesidad.

---

## Resumen de archivos para Dokploy

| Archivo | Uso |
|---------|-----|
| `Dockerfile` | Build multi-stage: Node (Vite) → Composer (PHP) → PHP-FPM + Nginx. |
| `.dockerignore` | Excluye de la imagen `.env`, `node_modules`, `vendor`, tests, etc. |
| `docker/nginx/default.conf` | Configuración Nginx para Laravel (raíz `public`, PHP-FPM en 9000). |
| `docker/entrypoint.sh` | Arranque: PHP-FPM, `artisan config/route/view cache`, `migrate`, luego Nginx. |

Con esto puedes desplegar en Dokploy usando solo el Dockerfile y el contexto `.` desde la raíz del repo.
