# Ejecutar la aplicación con Docker

Configuración básica para levantar **Administrador de Agentes** con Docker Compose (app + PostgreSQL).

En el contenedor, **Supervisord** mantiene en marcha PHP-FPM y Nginx; así el contenedor no se cierra (estado "exited").

## Requisitos

- Docker y Docker Compose instalados.
- Archivo `.env` con al menos `APP_KEY` (y opcionalmente `DB_PASSWORD`).

## Pasos

### 1. Crear `.env` (si no existe)

```bash
cp .env.example .env
```

### 2. Generar y configurar `APP_KEY`

```bash
php artisan key:generate --show
```

Copia la clave que salga (ej. `base64:xxx...`) y pégala en `.env` en la línea `APP_KEY=`.

Si no tienes PHP local, puedes poner temporalmente en `.env`:

```env
APP_KEY=base64:TU_CLAVE_AQUI
```

y generar una válida después con:

```bash
docker compose exec app php artisan key:generate
```

### 3. (Opcional) Contraseña de PostgreSQL

Por defecto se usa `secret`. Para otra contraseña, en `.env`:

```env
DB_PASSWORD=tu_password_seguro
```

O al levantar:

```bash
DB_PASSWORD=tu_password docker compose up -d
```

### 4. Levantar los servicios

```bash
docker compose up -d
```

- **App:** http://localhost:8080  
- **PostgreSQL:** puerto 5432 (solo desde el host si necesitas conectar un cliente).

### 5. Migraciones (primera vez)

```bash
docker compose exec app php artisan migrate --force
```

### 6. Parar

```bash
docker compose down
```

Con datos persistentes (volúmenes):

```bash
docker compose down
# Los datos de PostgreSQL y storage se mantienen
```

Para borrar también los volúmenes:

```bash
docker compose down -v
```

## Variables de entorno principales

| Variable     | Descripción                    | Por defecto (compose)   |
|-------------|--------------------------------|--------------------------|
| `APP_KEY`   | Clave de cifrado Laravel (obligatoria) | — (debes generarla) |
| `DB_PASSWORD` | Contraseña PostgreSQL        | `secret`                 |
| `APP_URL`   | URL pública de la app          | http://localhost:8080   |

El resto de variables se heredan de `.env`; el compose solo sobrescribe `DB_HOST=postgres`, `DB_*`, etc. para que la app use el contenedor de PostgreSQL.
