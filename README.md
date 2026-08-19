# Sura Agents

Panel de administración para agentes de IA con integración N8N, WhatsApp Business y ElevenLabs.

---

## Tabla de contenidos

- [Stack tecnológico](#stack-tecnológico)
- [Arquitectura](#arquitectura)
- [Módulos y funcionalidades](#módulos-y-funcionalidades)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Modelos de datos](#modelos-de-datos)
- [API REST](#api-rest)
- [Instalación](#instalación)
- [Desarrollo](#desarrollo)

---

## Stack tecnológico

| Capa | Tecnología |
|------|------------|
| **Backend** | Laravel 12 |
| **Frontend** | Vue 3 + Inertia.js + Vite |
| **Estilos** | Tailwind CSS |
| **Base de datos** | PostgreSQL (Supabase) |
| **Gráficos** | Chart.js + vue-chartjs |
| **Editor rich text** | Quill (@vueup/vue-quill) |

---

## Arquitectura

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              FRONTEND (Vue 3 + Inertia)                      │
├─────────────────────────────────────────────────────────────────────────────┤
│  Pages/                Components/           Layouts/                         │
│  ├─ Agents/            ├─ PrimaryButton     ├─ AuthenticatedLayout          │
│  ├─ Auth/               ├─ TextInput         └─ GuestLayout                   │
│  ├─ Dashboard           ├─ DangerButton                                       │
│  ├─ Clients             └─ ...                                                │
│  └─ ...                                                                      │
└─────────────────────────────────────────────────────────────────────────────┘
                                        │
                                        │ Inertia.js (SPA-like)
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         BACKEND (Laravel 12)                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│  Controllers/           Services/              Models/                        │
│  ├─ AgentController     ├─ ContactQueueService ├─ Agent                       │
│  ├─ ClientController    └─ ...                ├─ Client                      │
│  ├─ Api/                                                                      │
│  │   ├─ ClientApi                            └─ ...                           │
│  │   ├─ CollectData                                                           │
│  │   └─ CallbackRequestApi                                                    │
│  └─ ...                                                                      │
└─────────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  PostgreSQL (Supabase)  │  Storage (local)  │  Colas (database)               │
│  └─ Tablas principales │  └─ logos, etc.   │  └─ ProcessContactQueueJob     │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Flujo de datos

1. **Usuario** → Interactúa con Vue/Inertia
2. **Inertia** → Peticiones HTTP a Laravel (sin API REST para la UI)
3. **Laravel** → Controladores → Servicios → Modelos
4. **Modelos** → Eloquent ORM → PostgreSQL
5. **Respuesta** → JSON (Inertia) o PDF/descargas

### Integraciones externas

- **N8N**: Workflows, webhooks para mensajes y llamadas
- **WhatsApp Business**: Conversaciones, recolección de datos
- **ElevenLabs**: Llamadas de voz
- **Supabase**: Base de datos PostgreSQL

---

## Módulos y funcionalidades

### 1. Autenticación y roles

| Funcionalidad | Descripción |
|---------------|-------------|
| Login / Registro | Laravel Breeze con Inertia |
| Recuperación de contraseña | Email de reset |
| Roles | **Administrador** (acceso completo) y **Editor** (acceso operativo) |
| Permisos | Webhooks, endpoints y fuente de clientes solo para Administrador |

### 2. Dashboard

- Estadísticas: agentes, clientes, contactos
- Gráficos: agentes, clientes, contactos WhatsApp, contactos por llamada
- Métricas: mensajes y minutos de llamadas

### 3. Gestión de agentes

| Recurso | Acciones |
|---------|----------|
| Agentes | CRUD completo |
| Estados | Activo, Inactivo, Borrador |
| N8N | Webhook URL, workflow, nodos de prompt |
| Integración | Creación automática de workflows en N8N |

### 4. Configuración del agente (vista detallada)

Menú agrupado en 6 secciones:

| Grupo | Subsecciones | Descripción |
|-------|--------------|-------------|
| **Clientes** | Lista, Campos dinámicos, Fuente | Gestión de clientes, campos personalizados, endpoints de carga externa |
| **Configuración** | — | Info general, prompt del sistema, **API Keys** |
| **Integración** | Endpoints, Recolección WhatsApp | Webhooks, variables de recolección (solo admin) |
| **Comunicación** | Mensajes, Llamadas, Callbacks, Campañas | Plantillas de mensajes, ElevenLabs, colas de contacto |
| **Reportes** | — | Informes y métricas por agente |
| **Logs** | — | Registro de actividad de endpoints |

### 5. Clientes

- CRUD de clientes por agente
- **API REST** para crear/actualizar clientes desde N8N
- Campos dinámicos configurables
- Importación/exportación CSV
- Colas de contacto programadas
- Historial de mensajes WhatsApp
- Iniciar llamada o conversación WhatsApp
- Solicitudes de callback (programar llamada o mensaje)

### 6. Administración (solo admin)

- **Configuración general**: Logo, colores primarios
- **Usuarios**: CRUD de usuarios

### 7. API REST externa

| Endpoint | Método | Auth | Descripción |
|----------|--------|------|-------------|
| `/api/agents/{id}/clients` | POST | API Key | Crear o actualizar cliente (desde N8N) |
| `/api/agents/{id}/collect-data` | POST | API Key | Recolección de datos desde WhatsApp |
| `/api/agents/{id}/callback-requests` | POST | API Key | Programar callback (llamada o mensaje) |

**Autenticación API** (endpoints de agentes): `Authorization: Bearer {api_key}` o `X-Api-Key: {api_key}`  
Las API Keys se generan en **Configuración** del agente. Los ejemplos de body están en Configuración → API Keys → "Ver endpoints API".

---

## Estructura del proyecto

```
seguralia-agents/
├── app/
│   ├── Console/Commands/          # Comandos artisan
│   │   └── ProcessScheduledContactQueues.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/                # API externa
│   │   │   │   ├── ClientApiController.php
│   │   │   │   ├── CollectDataController.php
│   │   │   │   └── CallbackRequestApiController.php
│   │   │   └── ...
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Jobs/
│   │   └── ProcessContactQueueJob.php
│   ├── Models/                     # Modelos Eloquent
│   ├── Observers/
│   │   └── AgentObserver.php
│   ├── Policies/
│   └── Services/
│       └── ContactQueueService.php
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/
│   ├── js/
│   │   ├── app.js
│   │   ├── Components/             # 14 componentes Vue
│   │   ├── Layouts/
│   │   └── Pages/
│   │       ├── Agents/             # CRUD + Show con sub-módulos
│   │       ├── Auth/
│   │       ├── Dashboard.vue
│   │       ├── Docs/
│   │       ├── Profile/
│   │       ├── Settings/
│   │       └── Users/
│   ├── css/
│   └── views/
├── routes/
│   ├── web.php
│   ├── api.php
│   └── auth.php
└── storage/app/public/             # logos, archivos públicos
```

---

## Modelos de datos

### Entidades principales

| Modelo | Tabla | Descripción |
|--------|-------|-------------|
| `Agent` | agents | Agente IA con config N8N |
| `Client` | clients | Cliente asociado a un agente |
| `User` | users | Usuario del sistema |

### Configuración del agente

| Modelo | Tabla | Descripción |
|--------|-------|-------------|
| `AgentApiKey` | agent_api_keys | API Keys para autenticación |
| `AgentEndpoint` | agent_endpoints | Endpoints/webhooks |
| `AgentEndpointLog` | agent_endpoint_logs | Logs de actividad |
| `AgentClientField` | agent_client_fields | Campos dinámicos de clientes |
| `AgentClientSourceEndpoint` | agent_client_source_endpoints | Fuentes externas de clientes |
| `AgentDataVariable` | agent_data_variables | Variables de recolección WhatsApp |
| `AgentMessageConfig` | agent_message_configs | Configuración de mensajes |
| `AgentCallConfig` | agent_call_configs | Configuración ElevenLabs |

### Clientes y contacto

| Modelo | Tabla | Descripción |
|--------|-------|-------------|
| `ClientContactLog` | client_contact_logs | Historial de contactos |
| `ClientLoadDate` | client_load_dates | Fechas de carga/importación |
| `ClientCallbackRequest` | client_callback_requests | Solicitudes de callback programadas |
| `ContactQueue` | contact_queues | Colas de contacto programadas |

### Otros

| Modelo | Tabla | Descripción |
|--------|-------|-------------|
| `Setting` | settings | Configuración global de la app |
| `Transaction` | transactions | Transacciones (ej. llamadas) |
| `AgentWhatsappConversation` | Dinámica por agente | Conversaciones WhatsApp |

---

## API REST

### POST `/api/agents/{agent}/clients`

Crea o actualiza un cliente desde N8N u otro sistema externo. Si ya existe un cliente con el mismo `document` o `phone`, se actualiza.

**Headers:**
- `Authorization: Bearer {api_key}` o `X-Api-Key: {api_key}`
- `Content-Type: application/json`

**Body:**
```json
{
  "name": "Juan",
  "lastname": "Pérez",
  "email": "juan@ejemplo.com",
  "phone": "573001234567",
  "document_type": "CC",
  "document": "123456789",
  "custom_fields": { "edad": "30", "ciudad": "Bogotá" }
}
```

**Campos:** `name`, `lastname`, `email` (requeridos); `phone`, `document_type`, `document`, `custom_fields` (opcionales).

**Respuesta (201 creado):**
```json
{
  "success": true,
  "message": "Cliente creado",
  "client": {
    "id": 1,
    "name": "Juan",
    "lastname": "Pérez",
    "email": "juan@ejemplo.com",
    "phone": "573001234567",
    "document_type": "CC",
    "document": "123456789"
  }
}
```

### POST `/api/agents/{agent}/collect-data`

Recibe datos recolectados desde WhatsApp. Crea cliente si no existe (por `phone`) o actualiza sus `custom_fields`.

**Headers:**
- `Authorization: Bearer {api_key}` o `X-Api-Key: {api_key}`
- `Content-Type: application/json`

**Body:**
```json
{
  "phone": "573001234567",
  "documento": "123456789",
  "nombre": "Juan Pérez",
  "edad": "30"
}
```

**Campos:** `phone` (requerido, solo dígitos). El resto son variables definidas en Recolección datos WhatsApp del agente.

**Respuesta:**
```json
{
  "success": true,
  "message": "Datos recolectados correctamente"
}
```

---

## Instalación

### Requisitos

- PHP 8.2+
- Composer
- Node.js 18+
- Cuenta en [Supabase](https://supabase.com)

### Pasos

```bash
# Dependencias PHP
composer install

# Dependencias Node (--legacy-peer-deps por compatibilidad Vite 7)
npm install --legacy-peer-deps

# Variables de entorno
cp .env.example .env
php artisan key:generate
```

### Base de datos (Supabase)

1. Crear proyecto en [Supabase](https://database.new)
2. Copiar **Connection string** (Session pooler)
3. Configurar en `.env`:
   ```env
   DB_CONNECTION=pgsql
   DB_URL=postgres://postgres.[PROJECT_REF]:[PASSWORD]@aws-0-[REGION].pooler.supabase.com:5432/postgres
   DB_SSLMODE=require
   ```
4. Migrar:
   ```bash
   php artisan migrate --seed
   ```
5. Compilar assets:
   ```bash
   npm run build
   ```

---

## Desarrollo

```bash
# Servidor Laravel
php artisan serve

# En otra terminal: Vite (hot reload)
npm run dev
```

Acceso: [http://127.0.0.1:8000](http://127.0.0.1:8000)

### Usuarios de prueba (después del seed)

| Email | Contraseña | Rol |
|-------|------------|-----|
| admin@example.com | password | Administrador |
| editor@example.com | password | Editor |

### Colas (opcional)

```bash
php artisan queue:listen
```

---

## Licencia

Este proyecto utiliza Laravel, licenciado bajo [MIT](https://opensource.org/licenses/MIT).
