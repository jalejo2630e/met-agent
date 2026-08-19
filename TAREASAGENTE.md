# Checklist: Funcionalidades del Agente

## 1. Endpoints precargados (antes del contacto)

- [x] Crear tabla `agent_endpoints` (agent_id, name, url, method, headers, client_parameter, response_mapping)
- [x] Modelo `AgentEndpoint` con relación a Agent
- [x] UI: Menú en vista del agente para agregar/editar endpoints precargados
- [x] Selector de parámetro del cliente que dispara el endpoint
- [x] Headers configurables para autenticación (Authorization, API keys, etc.)
- [x] Botón Probar: body de ejemplo y validación de respuesta
- [ ] Lógica para llamar endpoint y enviar datos al agente en primer contacto (pendiente integración N8N/WhatsApp)

## 2. Configuración de contacto por mensaje (WhatsApp)

- [x] Crear tabla `agent_message_configs` (agent_id, webhook_url, schedule_config)
- [x] Modelo `AgentMessageConfig`
- [x] UI: Configurar webhook para mensajes
- [x] UI: Horarios de contacto (rango de horas)
- [x] UI: Días de la semana con offset (ej: Lunes +0h, Martes +24h)
- [x] UI: Días excluidos (festivos, días sin atención)

## 3. Configuración de contacto por llamada

- [x] Crear tabla `agent_call_configs` (agent_id, webhook_url, schedule_config)
- [x] Modelo `AgentCallConfig`
- [x] UI: Configurar webhook para llamadas
- [x] UI: Horarios de contacto
- [x] UI: Días de la semana con offset
- [x] UI: Días excluidos

## 4. Recolectar datos - Endpoint dinámico

- [x] Crear tabla `agent_data_variables` (agent_id, name, type, required)
- [x] Modelo `AgentDataVariable`
- [x] Crear tabla `agent_api_keys` (agent_id, key_hash, name) para credenciales API
- [x] Endpoint POST `/api/agents/{agent}/collect-data` con auth por API key
- [x] UI: Definir variables que recibirá el agente
- [x] Generación de API key por agente

## 5. Sección Clientes

- [x] Crear tabla `clients` (agent_id, name, lastname, email, document_type, document, custom_fields)
- [x] Crear tabla `agent_client_fields` (agent_id, field_name, field_type, required) para campos dinámicos
- [x] Modelos `Client` y `AgentClientField`
- [x] UI: CRUD de clientes por agente
- [x] UI: Configurar campos dinámicos del cliente para el agente
- [x] CRUD de clientes con campos base + dinámicos

---

**Progreso:** 5/5 secciones completadas (estructura y UI)
**Pendiente:** Integración real de endpoints precargados en flujo N8N/WhatsApp
