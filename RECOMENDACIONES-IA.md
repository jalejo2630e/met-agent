# IA en Colsanitas Agent — Laravel 13 + AI SDK

Documento de recomendaciones y guía de lo implementado tras migrar a **Laravel 13**
e incorporar el **AI SDK oficial de Laravel** (`laravel/ai`).

> **Contexto.** Hoy la IA vive fuera de Laravel: el prompt del agente se arma en
> `AgentPromptBuilder` y se sincroniza a **ElevenLabs** (voz) y **n8n** (WhatsApp).
> Las transcripciones se guardan en Supabase y solo se muestran; no se analizan.
> El AI SDK permite traer IA **dentro** de Laravel: análisis de llamadas, agentes
> conversacionales nativos, embeddings/búsqueda semántica, resúmenes, etc.

Proveedor por defecto: **OpenAI** (`config/ai.php → 'default' => 'openai'`). Es
multi‑proveedor: se puede cambiar por agente con los atributos `#[Provider(...)]` /
`#[Model(...)]` o el enum `Laravel\Ai\Enums\Lab`.

---

## 0. Estado del upgrade

| Ítem | Estado |
|------|--------|
| `laravel/framework` | **v13.x** (desde v12.50) — 0 breaking changes que afecten a este código |
| `laravel/ai` | **v0.11** instalado y configurado (`config/ai.php`) |
| PHP | requiere `^8.3` (Docker sigue en `php:8.4-fpm`) |
| Dockerfile | corre `migrate` + `optimize` al arranque (antes no lo hacía) |
| Cola / scheduler | ya existían en supervisord → los jobs de IA funcionan sin cambios |

Variables nuevas en `.env` (ver `.env.example`): `OPENAI_API_KEY`,
`TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_WHATSAPP_FROM`,
`CACHE_PREFIX`, `SESSION_COOKIE`.

---

## 1. Lo que YA quedó implementado

### 1.1 Análisis post‑llamada con salida estructurada (POC)

Convierte cada transcripción en datos accionables usando un agente de IA con
**structured output**.

- Agente: `app/Ai/Agents/CallAnalyst.php` (implementa `HasStructuredOutput`,
  `#[UseCheapestModel]`). Devuelve: `resumen`, `sentimiento`, `motivo_contacto`,
  `resultado`, `requiere_alerta`, `categoria_alerta`, `descripcion_alerta`.
- Servicio: `app/Services/CallAnalysisService.php` — guarda el resultado en la
  tabla local **`call_analyses`** (funciona aunque la transcripción venga de
  Supabase en solo lectura) y, si `requiere_alerta`, crea una **`AlertaLlamada`**
  con la categoría detectada (reutiliza la lógica de `Api/CallAlertApiController`).
- Job en cola: `app/Jobs/AnalyzeCallTranscriptJob.php`.
- Comando de backfill: `php artisan ai:analyze-calls {agent?} --limit=50 [--sync]`.
- Endpoint para el panel: `POST /agents/{agent}/call-analysis`
  (body: `conversation_id`, `transcript`, `phone?`). El modal de transcripción
  ya tiene la transcripción cargada; basta con enviarla aquí y pintar el resultado.
- Test: `tests/Feature/CallAnalysisTest.php` (usa `CallAnalyst::fake()`).

Esto aprovecha directamente el sistema de alertas existente
(`AlertaLlamada` / `AlertaCategoria`), que hoy se llena a mano.

### 1.2 Agente de WhatsApp/SMS NATIVO (reemplaza n8n) vía Twilio

Un agente conversacional que corre **dentro de Laravel** con el `system_prompt`
que ya configuras en el panel — sin depender de n8n para el canal de texto.

- Agente: `app/Ai/Agents/WhatsappAgent.php` (`Conversational`, con memoria por número).
- Webhook: `app/Http/Controllers/Api/TwilioMessageController.php` — valida la firma
  de Twilio, guarda el historial (`twilio_messages`) y responde con **TwiML**.

**URL del webhook a configurar en Twilio** (Messaging → *When a message comes in*, método **POST**):

```
https://TU_DOMINIO/api/agents/{AGENT_ID}/twilio/whatsapp
```

Pasos en Twilio:
1. WhatsApp Sender (o Sandbox) / número SMS → sección *Messaging*.
2. En **"A message comes in"** pega la URL de arriba con el ID del agente y método **POST**.
3. Define `TWILIO_AUTH_TOKEN` en `.env` para validar la firma (recomendado).
4. El prompt del bot se edita en el panel (config de mensajes del agente); el
   mismo `system_prompt` que ya usas para n8n/ElevenLabs alimenta a la IA nativa.

> El mismo endpoint sirve para **WhatsApp y SMS** (Twilio usa el mismo formato de
> webhook). La **voz en tiempo real sigue en ElevenLabs** (el AI SDK no hace
> telefonía conversacional en vivo).

---

## 2. Recomendaciones priorizadas (roadmap)

Ordenadas por relación valor/esfuerzo. Las dos primeras ya están implementadas.

1. ✅ **Análisis post‑llamada** → alertas + resumen automáticos (§1.1).
2. ✅ **Agente WhatsApp/SMS nativo** por Twilio, sin n8n (§1.2).
3. **Playground de prompt en el panel.** Botón "Probar" que ejecute
   `agent(instructions: $systemPrompt)->stream(...)` con el `system_prompt` real
   (el que arma `AgentPromptBuilder::buildFromSections`) antes de sincronizarlo a
   ElevenLabs/n8n. Encaja en `AgentConfigController`. Detecta prompts malos antes de producción.
4. **Búsqueda semántica de transcripciones / conversaciones.** Generar embeddings
   (`Str::of($texto)->toEmbeddings()` / `Embeddings::for([...])`) y consultar con
   `->whereVectorSimilarTo(...)`. Requiere habilitar **pgvector** en Supabase.
   Permite "buscar todas las llamadas donde el cliente mencionó X".
5. **Narrativa ejecutiva en reportes.** Añadir un resumen en lenguaje natural a
   `AgentOperationReportService::build()` y al correo `OperationReportMail`
   (`Str::summarize(...)` o un agente con structured output).
6. **QA / scoring de llamadas** contra una rúbrica → alimenta los widgets ya
   existentes (`CustomReportBuilderService`, métrica `value_counts`).
7. **Asistente de redacción de prompts** en el editor de secciones
   (greeting / behavior / business_rules) para que el operador escriba mejores prompts.
8. **Triage de la cola de contacto** (`ContactQueueService`): priorizar clientes con IA.
9. **Tools + MCP + sub‑agentes + Human Approval.** Dar herramientas al
   `WhatsappAgent` (consultar datos del cliente, agendar, escalar) con
   `HasTools`; usar `Approvable` para acciones sensibles (contexto salud). Este es
   el camino para retirar n8n por completo, no solo el prompt de texto.

---

## 3. Consideraciones importantes (compliance y costos)

- **Datos personales / de salud (Habeas Data — Ley 1581 de 2012).** Enviar
  transcripciones y mensajes a un proveedor de IA (OpenAI) implica transferencia de
  datos personales y, potencialmente, de salud. Evalúa: acuerdo de tratamiento de
  datos con el proveedor, minimización/enmascarado de PII antes de enviar, y/o usar
  un proveedor con residencia de datos (Azure OpenAI / Bedrock, ambos soportados por
  el enum `Lab`). El AI SDK permite cambiar de proveedor sin tocar la lógica.
- **Costos.** El análisis usa `#[UseCheapestModel]` para abaratar. Los embeddings y
  el volumen de mensajes tienen costo; cachea embeddings (`config/ai.php → caching`).
- **La voz sigue en ElevenLabs.** El AI SDK cubre texto, imágenes, audio (TTS/STT),
  embeddings y reranking, pero **no** telefonía conversacional en vivo.
- **pgvector.** La búsqueda semántica (§2.4) requiere `CREATE EXTENSION vector;` en
  la base de datos de Supabase.

---

## 4. Referencia rápida de configuración

```dotenv
# IA (obligatorio para análisis y agente de texto)
OPENAI_API_KEY=sk-...

# Twilio (canal de texto del agente nativo)
TWILIO_ACCOUNT_SID=AC...
TWILIO_AUTH_TOKEN=...
TWILIO_WHATSAPP_FROM=whatsapp:+14155238886
```

```bash
# Analizar en lote las llamadas sin analizar (origen interno)
php artisan ai:analyze-calls --limit=50            # encola
php artisan ai:analyze-calls 12 --sync             # agente 12, síncrono
```

Documentación del SDK: https://laravel.com/framework/docs/ai-sdk
