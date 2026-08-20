<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    agent: Object,
});

const page = usePage();
const promptErrors = computed(() => page.props.errors || {});
const whatsappConversationsSourceLabel = computed(() =>
    page.props.whatsapp_conversations_source === 'supabase' ? 'Supabase (solo lectura)' : 'base de datos de la app'
);

// --- Prompt del sistema (un solo campo = instructions del agente de texto nativo) ---
const systemPrompt = ref(props.agent?.prompt_configuration?.system_prompt ?? '');
const aiProvider = ref(props.agent?.prompt_configuration?.ai_provider ?? '');
const aiModel = ref(props.agent?.prompt_configuration?.ai_model ?? '');
watch(
    () => [props.agent?.id, props.agent?.prompt_configuration],
    () => {
        systemPrompt.value = props.agent?.prompt_configuration?.system_prompt ?? '';
        aiProvider.value = props.agent?.prompt_configuration?.ai_provider ?? '';
        aiModel.value = props.agent?.prompt_configuration?.ai_model ?? '';
    },
    { deep: true },
);

// Sugerencias de modelo por proveedor (el campo es libre: puedes escribir cualquier modelo).
const modelSuggestions = {
    openai: ['gpt-4.1', 'gpt-4.1-mini', 'gpt-4.1-nano', 'gpt-4o', 'gpt-4o-mini'],
    anthropic: ['claude-sonnet-4-5', 'claude-haiku-4-5', 'claude-opus-4-1'],
    gemini: ['gemini-2.5-flash', 'gemini-2.5-pro'],
    groq: ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant'],
    deepseek: ['deepseek-chat', 'deepseek-reasoner'],
    mistral: ['mistral-large-latest', 'mistral-small-latest'],
    xai: ['grok-3', 'grok-3-mini'],
};
const currentModelSuggestions = computed(() => modelSuggestions[aiProvider.value] ?? []);

const submittingPrompt = ref(false);
const submitPrompt = () => {
    submittingPrompt.value = true;
    router.put(route('agents.prompt-config.update', props.agent), {
        system_prompt: systemPrompt.value ?? '',
        ai_provider: aiProvider.value || null,
        ai_model: aiModel.value || null,
    }, {
        preserveScroll: true,
        onFinish: () => { submittingPrompt.value = false; },
    });
};

// Webhook del agente de IA NATIVO (Twilio) — reemplaza n8n en el canal de texto.
const appOrigin = computed(() => (typeof window !== 'undefined' ? window.location.origin : ''));
const twilioWebhookUrl = computed(() => `${appOrigin.value}/api/agents/${props.agent?.id}/twilio/whatsapp`);
const webhookCopied = ref(false);
async function copyWebhook() {
    try {
        await navigator.clipboard.writeText(twilioWebhookUrl.value);
        webhookCopied.value = true;
        setTimeout(() => { webhookCopied.value = false; }, 2000);
    } catch (e) {
        // El usuario puede copiar manualmente si el navegador bloquea el portapapeles.
    }
}

// --- Plantillas de WhatsApp en Twilio (Content API) ---
const twilioConfigured = ref(true);
const twilioTemplates = ref([]);
const loadingTemplates = ref(false);
const creatingTemplate = ref(false);
const templateResult = ref(null);
const templateError = ref('');
// Literales para mostrar {{1}} / {{2}} en el texto de ayuda (evita romper el parser de Vue).
const v1 = '{{1}}';
const v2 = '{{2}}';
const templateForm = ref({
    friendly_name: '',
    language: 'es',
    category: 'UTILITY',
    body: '',
    submit_whatsapp: true,
});

async function loadTwilioTemplates() {
    loadingTemplates.value = true;
    try {
        const { data } = await axios.get(route('agents.twilio.templates.index', props.agent));
        twilioConfigured.value = data.configured !== false;
        twilioTemplates.value = data.templates ?? [];
    } catch (e) {
        // silencioso
    } finally {
        loadingTemplates.value = false;
    }
}

async function createTwilioTemplate() {
    templateError.value = '';
    templateResult.value = null;
    const f = templateForm.value;
    const matches = [...String(f.body).matchAll(/\{\{\s*(\d+)\s*\}\}/g)].map((m) => m[1]);
    const variables = {};
    [...new Set(matches)].forEach((n) => { variables[n] = ''; });

    creatingTemplate.value = true;
    try {
        const { data } = await axios.post(route('agents.twilio.templates.store', props.agent), {
            friendly_name: f.friendly_name,
            language: f.language,
            category: f.category,
            body: f.body,
            submit_whatsapp: f.submit_whatsapp,
            variables,
        });
        templateResult.value = data.result;
        f.friendly_name = '';
        f.body = '';
        await loadTwilioTemplates();
    } catch (e) {
        templateError.value = e?.response?.data?.error || 'No se pudo crear la plantilla.';
    } finally {
        creatingTemplate.value = false;
    }
}

onMounted(loadTwilioTemplates);

// --- Mensajes (webhook, plantillas) ---
const config = computed(() => props.agent?.message_config || {});
// Campos que una variable de plantilla puede referenciar (campos dinámicos del cliente)
const dynamicFields = computed(() => props.agent?.client_fields || props.agent?.clientFields || []);

// Normaliza las plantillas para el formulario. Cada variable usa un `source`
// combinado "tipo:clave" (ej. "field:name" o "custom_field:ciudad") para el <select>.
function normalizePlantillas(p) {
    const list = Array.isArray(p) && p.length ? p : [{ id: '', name: '' }];
    return list.map((x) => ({
        id: x.id ?? '',
        name: x.name ?? '',
        from_twilio: !!x.from_twilio,
        body: x.body ?? '',
        has_variables: !!x.has_variables,
        variables: Array.isArray(x.variables)
            ? x.variables.map((v) => ({
                name: v.name ?? '',
                source: v.source_type && v.source_key ? `${v.source_type}:${v.source_key}` : '',
            }))
            : [],
    }));
}

// Plantillas de Twilio ya aprobadas para WhatsApp (para el selector de "agregar desde Twilio").
const approvedTwilioTemplates = computed(
    () => (twilioTemplates.value || []).filter((t) => t.sid)
);
const selectedTwilioSid = ref('');

// Agrega una plantilla precargada desde Twilio: id/nombre y variables detectadas.
const addPlantillaFromTwilio = () => {
    const t = approvedTwilioTemplates.value.find((x) => x.sid === selectedTwilioSid.value);
    if (!t) return;
    const vars = Array.isArray(t.variables) ? t.variables : [];
    // Evitar duplicar si ya está agregada.
    const existing = form.plantillas.find((p) => p.id === t.sid);
    if (existing) {
        existing.from_twilio = true;
        existing.name = t.friendly_name || existing.name;
        existing.body = t.body || '';
        existing.has_variables = vars.length > 0;
        existing.variables = vars.map((v) => ({ name: String(v), source: '' }));
    } else {
        form.plantillas.push({
            id: t.sid,
            name: t.friendly_name || t.sid,
            from_twilio: true,
            body: t.body || '',
            has_variables: vars.length > 0,
            variables: vars.map((v) => ({ name: String(v), source: '' })),
        });
    }
    // Quitar filas completamente vacías (p. ej. la fila inicial en blanco).
    form.plantillas = form.plantillas.filter(
        (p) => p.id?.trim() || p.name?.trim() || p.from_twilio || (p.variables && p.variables.length)
    );
    selectedTwilioSid.value = '';
};

const form = useForm({
    webhook_url: config.value.webhook_url ?? '',
    plantillas: normalizePlantillas(config.value.plantillas),
    default_plantilla_id: config.value.default_plantilla_id ?? '',
});

watch(config, (c) => {
    form.webhook_url = c?.webhook_url ?? '';
    form.plantillas = normalizePlantillas(c?.plantillas);
    form.default_plantilla_id = c?.default_plantilla_id ?? '';
}, { deep: true });

const addPlantilla = () => {
    form.plantillas.push({ id: '', name: '', from_twilio: false, body: '', has_variables: false, variables: [] });
};

const removePlantilla = (idx) => {
    form.plantillas.splice(idx, 1);
    if (form.plantillas.length === 0) {
        form.plantillas.push({ id: '', name: '', from_twilio: false, body: '', has_variables: false, variables: [] });
    }
};

const addVariable = (pIdx) => {
    if (!Array.isArray(form.plantillas[pIdx].variables)) form.plantillas[pIdx].variables = [];
    form.plantillas[pIdx].variables.push({ name: '', source: '' });
};

const removeVariable = (pIdx, vIdx) => {
    form.plantillas[pIdx].variables.splice(vIdx, 1);
};

// Muestra el placeholder como {{nombre}} sin escribir llaves literales en el
// template (romperían el parser de Vue).
const placeholderLabel = (name) => `{{${name}}}`;

const canEditWebhook = () => page.props.auth?.canAccessWebhooksAndTechnical === true;

const submit = () => {
    const data = { ...form.data() };
    data.plantillas = (data.plantillas || [])
        .filter((p) => p?.id?.trim())
        .map((p) => {
            const variables = p.has_variables
                ? (p.variables || [])
                    .filter((v) => (v?.name || '').trim() && (v?.source || '').trim())
                    .map((v) => {
                        const sep = v.source.indexOf(':');
                        return {
                            name: v.name.trim(),
                            source_type: v.source.slice(0, sep),
                            source_key: v.source.slice(sep + 1),
                        };
                    })
                : [];
            return {
                id: p.id.trim(),
                name: (p.name || '').trim(),
                from_twilio: !!p.from_twilio,
                body: (p.body || '').slice(0, 2000),
                has_variables: !!p.has_variables && variables.length > 0,
                variables,
            };
        });

    const defaultId = (data.default_plantilla_id || '').trim();
    if (defaultId && !data.plantillas.some((p) => p.id === defaultId)) {
        data.default_plantilla_id = '';
    } else {
        data.default_plantilla_id = defaultId || null;
    }

    form.transform(() => data).put(route('agents.message-config.update', props.agent));
};
</script>

<template>
    <div class="space-y-6">
        <!-- Agente de IA nativo (Twilio) — reemplaza n8n en el canal de texto -->
        <div class="overflow-hidden rounded-lg border border-emerald-200 bg-white">
            <div class="border-b border-emerald-200 bg-emerald-50/50 px-6 py-4">
                <h3 class="text-lg font-semibold text-[#33475b]">Agente de IA nativo · WhatsApp/SMS por Twilio</h3>
                <p class="mt-1 text-sm text-[#425b76]">
                    La IA responde <strong>dentro de esta app</strong> usando el prompt de abajo, <strong>sin n8n</strong>.
                    Pega esta URL en Twilio → <em>Messaging</em> → “When a message comes in” con método <strong>POST</strong>.
                </p>
            </div>
            <div class="space-y-3 p-6">
                <InputLabel value="Webhook para Twilio" />
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        :value="twilioWebhookUrl"
                        readonly
                        class="min-w-0 flex-1 rounded-md border-[#e3e8ee] bg-[#f5f8fa] font-mono text-sm text-[#33475b]"
                        @focus="(e) => e.target.select()"
                    />
                    <button
                        type="button"
                        class="shrink-0 rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                        @click="copyWebhook"
                    >
                        {{ webhookCopied ? '¡Copiado!' : 'Copiar' }}
                    </button>
                </div>
                <p class="text-xs text-[#64748b]">
                    Requiere <code class="rounded bg-[#e3e8ee] px-1">OPENAI_API_KEY</code> y las variables <code class="rounded bg-[#e3e8ee] px-1">TWILIO_*</code> en el servidor
                    (define <code class="rounded bg-[#e3e8ee] px-1">TWILIO_AUTH_TOKEN</code> para validar la firma). Sirve para WhatsApp y SMS; la voz sigue en ElevenLabs.
                </p>
            </div>
        </div>

        <!-- Prompt del sistema (un solo campo = instructions del agente de texto) -->
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="border-b border-[#e3e8ee] px-6 py-4">
                <h3 class="text-lg font-semibold text-[#33475b]">Prompt del sistema (agente de texto)</h3>
                <p class="mt-1 text-sm text-[#425b76]">
                    Instrucciones completas del agente de WhatsApp/Twilio en un <strong>solo campo</strong>.
                    Es el mismo <em>system prompt</em> que usa el agente de IA nativo de Laravel.
                </p>
            </div>
            <form @submit.prevent="submitPrompt" class="space-y-4 p-6">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Proveedor de IA (LLM)" />
                        <select v-model="aiProvider" class="mt-1 block w-full rounded-md border-[#e3e8ee] text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                            <option value="">Por defecto (config del servidor)</option>
                            <option value="openai">OpenAI (GPT)</option>
                            <option value="anthropic">Anthropic (Claude)</option>
                            <option value="gemini">Google (Gemini)</option>
                            <option value="groq">Groq</option>
                            <option value="deepseek">DeepSeek</option>
                            <option value="mistral">Mistral</option>
                            <option value="xai">xAI (Grok)</option>
                            <option value="openrouter">OpenRouter</option>
                            <option value="ollama">Ollama</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="ai_model" value="Modelo" />
                        <input
                            id="ai_model"
                            v-model="aiModel"
                            list="ai-model-suggestions"
                            type="text"
                            class="mt-1 block w-full rounded-md border-[#e3e8ee] font-mono text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                            placeholder="ej. gpt-4.1-mini"
                        />
                        <datalist id="ai-model-suggestions">
                            <option v-for="m in currentModelSuggestions" :key="m" :value="m" />
                        </datalist>
                        <p class="mt-1 text-xs text-[#425b76]">Vacío = modelo por defecto del proveedor. Requiere su API key en el servidor.</p>
                    </div>
                </div>
                <div>
                    <InputLabel for="system_prompt" value="System prompt" />
                    <textarea
                        id="system_prompt"
                        v-model="systemPrompt"
                        rows="14"
                        class="mt-1 block w-full rounded-md border-[#e3e8ee] font-mono text-sm shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                        placeholder="Eres el asistente de [empresa]. Tu rol es... Tono y estilo: ... Reglas del negocio: ... Solo respondes sobre [ámbito]; si te preguntan algo fuera de contexto, indícalo de forma amable. No compartas datos sensibles ni inventes información."
                    />
                    <p class="mt-1 text-xs text-[#425b76]">Incluye aquí el rol, tono, reglas del negocio y límites del agente. Todo el texto se envía como instrucciones al modelo.</p>
                    <InputError
                        v-if="promptErrors.system_prompt"
                        :message="Array.isArray(promptErrors.system_prompt) ? promptErrors.system_prompt[0] : promptErrors.system_prompt"
                        class="mt-2"
                    />
                </div>
                <div class="flex justify-end border-t border-[#e3e8ee] pt-4">
                    <PrimaryButton type="submit" :disabled="submittingPrompt">
                        {{ submittingPrompt ? 'Guardando...' : 'Guardar prompt' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>

        <!-- Plantillas de WhatsApp en Twilio (Content API) -->
        <div class="overflow-hidden rounded-lg border border-emerald-200 bg-white">
            <div class="border-b border-emerald-200 bg-emerald-50/50 px-6 py-4">
                <h3 class="text-lg font-semibold text-[#33475b]">Plantillas de WhatsApp (Twilio)</h3>
                <p class="mt-1 text-sm text-[#425b76]">Crea plantillas directamente en Twilio (Content API) y envíalas a aprobación de WhatsApp, sin salir del panel.</p>
            </div>
            <div class="space-y-4 p-6">
                <p v-if="!twilioConfigured" class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    Twilio no está configurado. Define <code class="rounded bg-white px-1">TWILIO_ACCOUNT_SID</code> y <code class="rounded bg-white px-1">TWILIO_AUTH_TOKEN</code> en el servidor.
                </p>

                <form v-else @submit.prevent="createTwilioTemplate" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Nombre (friendly_name)" />
                            <TextInput v-model="templateForm.friendly_name" type="text" class="mt-1 block w-full" placeholder="ej. recordatorio_cita" required />
                        </div>
                        <div class="flex gap-3">
                            <div>
                                <InputLabel value="Idioma" />
                                <select v-model="templateForm.language" class="mt-1 rounded-md border-[#e3e8ee] text-sm">
                                    <option value="es">es</option>
                                    <option value="es_CO">es_CO</option>
                                    <option value="en">en</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Categoría" />
                                <select v-model="templateForm.category" class="mt-1 rounded-md border-[#e3e8ee] text-sm">
                                    <option value="UTILITY">UTILITY</option>
                                    <option value="MARKETING">MARKETING</option>
                                    <option value="AUTHENTICATION">AUTHENTICATION</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Cuerpo del mensaje" />
                        <textarea
                            v-model="templateForm.body"
                            rows="4"
                            class="mt-1 block w-full rounded-md border-[#e3e8ee] text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                            placeholder="Hola, te recordamos tu cita..."
                            required
                        />
                        <p class="mt-1 text-xs text-[#64748b]">
                            Usa <code class="rounded bg-[#e3e8ee] px-1">{{ v1 }}</code>, <code class="rounded bg-[#e3e8ee] px-1">{{ v2 }}</code>… para las variables dinámicas.
                        </p>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="templateForm.submit_whatsapp" type="checkbox" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                        Enviar a aprobación de WhatsApp al crear
                    </label>
                    <div class="flex flex-wrap items-center gap-3">
                        <PrimaryButton type="submit" :disabled="creatingTemplate">
                            {{ creatingTemplate ? 'Creando...' : 'Crear plantilla en Twilio' }}
                        </PrimaryButton>
                        <span v-if="templateResult" class="text-sm text-emerald-700">Creada: <code class="rounded bg-emerald-50 px-1">{{ templateResult.sid }}</code></span>
                        <span v-if="templateError" class="text-sm text-red-600">{{ templateError }}</span>
                    </div>
                </form>

                <div v-if="twilioConfigured">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-medium text-gray-700">Plantillas existentes</h4>
                        <button type="button" class="text-xs font-medium text-emerald-600 hover:text-emerald-700" @click="loadTwilioTemplates">Actualizar</button>
                    </div>
                    <p v-if="loadingTemplates" class="mt-2 text-xs text-gray-400">Cargando…</p>
                    <p v-else-if="!twilioTemplates.length" class="mt-2 text-xs text-gray-400">Aún no hay plantillas.</p>
                    <ul v-else class="mt-2 divide-y divide-[#eef2f6] rounded-md border border-[#eef2f6]">
                        <li v-for="t in twilioTemplates" :key="t.sid" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm">
                            <span class="font-medium text-[#33475b]">{{ t.friendly_name || t.sid }}</span>
                            <span class="font-mono text-xs text-gray-400">{{ t.sid }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="t.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : (t.status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700')"
                            >{{ t.status }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Configuración de contacto por mensaje -->
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Configuración de contacto por mensaje</h3>
                <p class="mt-1 text-sm text-gray-500">Webhook y plantillas para contactar por WhatsApp/mensaje. La configuración de campañas masivas está en la pestaña <strong>Campañas</strong>.</p>

                <div v-if="agent.whatsapp_conversations_table" class="mt-4 rounded-lg border border-[#e3e8ee] bg-[#f5f8fa] px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wider text-[#425b76]">Tabla de conversaciones WhatsApp</p>
                    <p class="mt-1 font-mono text-sm text-[#33475b]" :title="agent.whatsapp_conversations_table">{{ agent.whatsapp_conversations_table }}</p>
                    <p class="mt-1 text-xs text-[#64748b]">
                        Origen: <strong>{{ whatsappConversationsSourceLabel }}</strong> (Configuración general). Solo lectura si es Supabase; con BD interna la tabla se crea al crear la empresa.
                    </p>
                </div>

                <form @submit.prevent="submit" class="mt-6 space-y-6">
                    <div v-if="canEditWebhook()">
                        <InputLabel value="Webhook URL de salida (envío de campañas / contacto manual)" />
                        <p class="mt-0.5 text-xs text-[#425b76]">URL a la que la app envía mensajes salientes. Los mensajes <strong>entrantes</strong> los atiende el agente de IA nativo por el webhook de Twilio de arriba.</p>
                        <TextInput v-model="form.webhook_url" type="url" class="mt-1 block w-full" placeholder="https://..." />
                        <InputError :message="form.errors.webhook_url" />
                    </div>

                    <div class="rounded-lg border border-emerald-200 bg-emerald-50/30 p-4">
                        <h4 class="text-sm font-medium text-gray-700">Plantillas (id_plantilla)</h4>
                        <p class="mt-1 text-xs text-gray-500">
                            Elige una plantilla real de Twilio/Meta (se detectan sus variables automáticamente) o agrégala manualmente.
                            Mapea cada variable a un dato del cliente; al enviar se resuelven por cliente y se mandan al webhook en <code class="rounded bg-white px-1">variables: [{ name, value }]</code>.
                        </p>

                        <!-- Agregar plantilla desde Twilio (Content API) -->
                        <div class="mt-3 flex flex-wrap items-center gap-2 rounded-md border border-dashed border-emerald-300 bg-white p-2">
                            <span class="text-xs font-medium text-gray-600">Agregar desde Twilio:</span>
                            <select v-model="selectedTwilioSid" class="min-w-[220px] rounded-md border-[#e3e8ee] text-sm">
                                <option value="">— Selecciona una plantilla de Twilio —</option>
                                <option v-for="t in approvedTwilioTemplates" :key="t.sid" :value="t.sid">
                                    {{ t.friendly_name || t.sid }}{{ t.status && t.status !== 'approved' ? ` (${t.status})` : '' }}
                                </option>
                            </select>
                            <button type="button" :disabled="!selectedTwilioSid" class="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-600 hover:bg-emerald-50 disabled:opacity-40" @click="addPlantillaFromTwilio">Agregar</button>
                            <button type="button" class="text-xs text-emerald-700 underline" @click="loadTwilioTemplates">{{ loadingTemplates ? 'Cargando…' : 'Actualizar lista' }}</button>
                            <span v-if="!approvedTwilioTemplates.length && !loadingTemplates" class="text-xs text-gray-400">No hay plantillas en Twilio (créalas en la sección de arriba).</span>
                        </div>

                        <div class="mt-3 space-y-3">
                            <div v-for="(p, idx) in form.plantillas" :key="idx" class="rounded border border-[#e3e8ee] bg-white p-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span v-if="p.from_twilio" class="rounded bg-emerald-100 px-1.5 py-0.5 text-[11px] font-medium text-emerald-700" title="Plantilla obtenida de Twilio">Twilio</span>
                                    <input v-model="p.id" type="text" placeholder="ID plantilla (ej. bienvenida_001)" :readonly="p.from_twilio" :class="['min-w-[140px] rounded-md border-[#e3e8ee] text-sm', p.from_twilio ? 'bg-gray-50 text-gray-500' : '']" />
                                    <input v-model="p.name" type="text" placeholder="Nombre (ej. Bienvenida)" class="min-w-[120px] rounded-md border-[#e3e8ee] text-sm" />
                                    <button type="button" class="ml-auto text-red-600 hover:text-red-700" title="Quitar plantilla" @click="removePlantilla(idx)">×</button>
                                </div>

                                <p v-if="p.body" class="mt-2 whitespace-pre-wrap rounded bg-gray-50 px-2 py-1 text-xs text-gray-600">{{ p.body }}</p>

                                <!-- Manual: activar variables. En Twilio se detectan solas. -->
                                <label v-if="!p.from_twilio" class="mt-2 inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input v-model="p.has_variables" type="checkbox" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                                    Tiene variables dinámicas
                                </label>

                                <div v-if="p.has_variables" class="mt-2 rounded-md border border-dashed border-emerald-200 bg-emerald-50/40 p-2">
                                    <p class="mb-2 text-xs text-gray-500">
                                        {{ p.from_twilio ? 'Variables detectadas en la plantilla. Asigna a cada una un dato del cliente.' : 'Define cada variable (en orden) y el valor del cliente al que hace referencia.' }}
                                    </p>
                                    <div class="space-y-2">
                                        <div v-for="(v, vIdx) in p.variables" :key="vIdx" class="flex flex-wrap items-center gap-2">
                                            <span v-if="p.from_twilio" class="min-w-[64px] rounded border border-[#e3e8ee] bg-white px-2 py-1 font-mono text-xs text-gray-600">{{ placeholderLabel(v.name) }}</span>
                                            <template v-else>
                                                <span class="text-xs font-medium text-gray-400">{{ vIdx + 1 }}.</span>
                                                <input v-model="v.name" type="text" placeholder="Nombre variable (ej. nombre)" class="min-w-[140px] rounded-md border-[#e3e8ee] text-sm" />
                                            </template>
                                            <span class="text-xs text-gray-400">→</span>
                                            <select v-model="v.source" class="min-w-[180px] rounded-md border-[#e3e8ee] text-sm">
                                                <option value="">— Selecciona el valor —</option>
                                                <optgroup label="Campos del cliente">
                                                    <option value="field:name">Nombre</option>
                                                    <option value="field:lastname">Apellido</option>
                                                    <option value="field:phone">Teléfono</option>
                                                    <option value="field:email">Email</option>
                                                    <option value="field:document_type">Tipo de documento</option>
                                                    <option value="field:document">Documento</option>
                                                </optgroup>
                                                <optgroup v-if="dynamicFields.length" label="Campos dinámicos">
                                                    <option v-for="f in dynamicFields" :key="f.id ?? f.field_name" :value="`custom_field:${f.field_name}`">{{ f.field_name }}</option>
                                                </optgroup>
                                                <optgroup label="Formulario">
                                                    <option value="special:form_url">URL del formulario</option>
                                                </optgroup>
                                            </select>
                                            <button v-if="!p.from_twilio" type="button" class="text-red-600 hover:text-red-700" title="Quitar variable" @click="removeVariable(idx, vIdx)">×</button>
                                        </div>
                                    </div>
                                    <button v-if="!p.from_twilio" type="button" class="mt-2 rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-600 hover:bg-emerald-50" @click="addVariable(idx)">+ Agregar variable</button>
                                </div>
                            </div>
                            <button type="button" class="rounded border border-emerald-300 px-2 py-1 text-sm text-emerald-600 hover:bg-emerald-50" @click="addPlantilla">+ Agregar plantilla manual</button>
                        </div>
                        <div v-if="form.plantillas?.filter((p) => p?.id?.trim()).length" class="mt-3">
                            <InputLabel value="Plantilla principal (para contacto manual)" />
                            <select v-model="form.default_plantilla_id" class="mt-1 rounded-md border-[#e3e8ee] shadow-sm">
                                <option value="">— Sin plantilla por defecto —</option>
                                <option v-for="p in form.plantillas.filter((x) => x?.id?.trim())" :key="p.id" :value="p.id">{{ p.name || p.id }}</option>
                            </select>
                        </div>
                    </div>

                    <PrimaryButton :disabled="form.processing">Guardar configuración</PrimaryButton>
                </form>
            </div>
        </div>
    </div>
</template>
