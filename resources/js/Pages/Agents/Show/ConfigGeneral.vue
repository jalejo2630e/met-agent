<script setup>
import { computed, ref } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    agent: Object,
});

const page = usePage();
const showApiEndpoints = ref(false);
const apiBaseUrl = computed(() => (typeof window !== 'undefined' ? window.location.origin : ''));
const apiClientsUrl = computed(() => `${apiBaseUrl.value}/api/agents/${props.agent?.id}/clients`);
const apiClientByPhoneUrl = computed(() => `${apiBaseUrl.value}/api/agents/${props.agent?.id}/clients/by-phone`);

const apiKeyForm = useForm({ name: 'Producción' });
const generateKey = (e) => {
    e?.preventDefault?.();
    apiKeyForm.post(route('agents.api-keys.store', props.agent));
};

const deleteApiKey = (key) => {
    if (confirm('¿Eliminar esta API Key?')) {
        router.delete(route('agents.api-keys.destroy', [props.agent, key]));
    }
};

const generatedApiKey = computed(() => {
    const success = page.props?.flash?.success;
    if (typeof success !== 'string') return '';
    const marker = 'Cópiala ahora (no se mostrará de nuevo): ';
    const idx = success.indexOf(marker);
    if (idx === -1) return '';
    return success.slice(idx + marker.length).trim();
});

const copiedApiKey = ref(false);
const copyGeneratedApiKey = async () => {
    if (!generatedApiKey.value) return;
    try {
        if (navigator?.clipboard?.writeText) {
            await navigator.clipboard.writeText(generatedApiKey.value);
        } else {
            const el = document.createElement('textarea');
            el.value = generatedApiKey.value;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
        }
        copiedApiKey.value = true;
        setTimeout(() => {
            copiedApiKey.value = false;
        }, 1800);
    } catch (_) {
        copiedApiKey.value = false;
    }
};
</script>

<template>
    <div class="space-y-6">
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-[#33475b]">Información general</h3>
                <dl class="mt-4 space-y-3">
                    <div>
                        <dt class="text-sm font-medium text-[#425b76]">Descripción</dt>
                        <dd class="mt-1 text-sm text-[#33475b]">
                            {{ agent.description || 'Sin descripción' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-[#425b76]">Creado por</dt>
                        <dd class="mt-1 text-sm text-[#33475b]">{{ agent.user?.name ?? '-' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-[#33475b]">API Keys</h3>
                <p class="mt-1 text-sm text-[#425b76]">
                    Credenciales para autenticar las peticiones a los endpoints de la empresa (recolección de datos, etc.).
                </p>
                <form @submit.prevent="generateKey" class="mt-4 flex gap-2">
                    <TextInput
                        v-model="apiKeyForm.name"
                        class="max-w-xs"
                        placeholder="Nombre (ej: Producción)"
                        required
                    />
                    <PrimaryButton type="submit" :disabled="apiKeyForm.processing">
                        Generar API Key
                    </PrimaryButton>
                </form>

                <div v-if="generatedApiKey" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Nueva API Key</p>
                    <p class="mt-1 text-xs text-amber-700">Cópiala ahora, luego no se podrá volver a ver completa.</p>
                    <div class="mt-2 flex items-center gap-2">
                        <code class="block flex-1 break-all rounded bg-white px-2 py-1 font-mono text-xs text-[#33475b]">{{ generatedApiKey }}</code>
                        <button
                            type="button"
                            class="rounded border border-amber-300 bg-white px-3 py-1.5 text-xs font-medium text-amber-800 hover:bg-amber-100"
                            @click="copyGeneratedApiKey"
                        >
                            {{ copiedApiKey ? 'Copiada' : 'Copiar' }}
                        </button>
                    </div>
                </div>

                <div v-if="agent.api_keys?.length" class="mt-6">
                    <ul class="divide-y divide-gray-200">
                        <li
                            v-for="key in agent.api_keys"
                            :key="key.id"
                            class="flex items-center justify-between py-2"
                        >
                            <span class="font-mono text-sm">{{ key.name }} — {{ key.key_prefix }}...</span>
                            <button type="button" class="text-red-600 hover:text-red-800" @click="deleteApiKey(key)">
                                Eliminar
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="mt-6 border-t border-[#e3e8ee] pt-6">
                    <button
                        type="button"
                        class="flex items-center gap-2 text-sm font-medium text-[#425b76] hover:text-[#33475b]"
                        @click="showApiEndpoints = !showApiEndpoints"
                    >
                        {{ showApiEndpoints ? 'Ocultar' : 'Ver' }} endpoints API
                        <svg
                            :class="['h-4 w-4 transition', showApiEndpoints ? 'rotate-180' : '']"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div v-show="showApiEndpoints" class="mt-4 space-y-6">
                        <!-- 1. Crear cliente -->
                        <div>
                            <p class="text-xs font-medium uppercase text-[#425b76]">1. Crear cliente (sistema externo)</p>
                            <code class="mt-1 block break-all rounded bg-[#e3e8ee] px-3 py-2 text-sm">{{ apiClientsUrl }}</code>
                            <p class="mt-2 text-xs text-[#425b76]">POST — Crea o actualiza cliente por document/phone</p>
                            <p class="mt-1 text-xs font-medium text-[#33475b]">Body ejemplo:</p>
                            <pre class="mt-1 overflow-x-auto rounded bg-[#33475b] p-3 text-xs text-[#e3e8ee]">{
  "name": "Juan",
  "lastname": "Pérez",
  "email": "juan@ejemplo.com",
  "phone": "573001234567",
  "document_type": "CC",
  "document": "123456789",
  "custom_fields": { "edad": "30", "ciudad": "Bogotá" }
}</pre>
                            <p class="mt-1 text-xs text-[#425b76]">Requeridos: name, lastname, email. Opcionales: phone, document_type, document, custom_fields</p>
                        </div>

                        <!-- 2. Recolección datos -->
                        <div>
                            <p class="text-xs font-medium uppercase text-[#425b76]">2. Recolección datos WhatsApp</p>
                            <code class="mt-1 block break-all rounded bg-[#e3e8ee] px-3 py-2 text-sm">{{ apiBaseUrl }}/api/agents/{{ agent?.id }}/collect-data</code>
                            <p class="mt-2 text-xs text-[#425b76]">POST — Guarda datos recolectados en conversación (crea cliente si no existe)</p>
                            <p class="mt-1 text-xs font-medium text-[#33475b]">Body ejemplo:</p>
                            <pre class="mt-1 overflow-x-auto rounded bg-[#33475b] p-3 text-xs text-[#e3e8ee]">{
  "phone": "573001234567",
  "documento": "123456789",
  "nombre": "Juan Pérez",
  "edad": "30"
}</pre>
                            <p class="mt-1 text-xs text-[#425b76]">Requerido: phone (solo dígitos). El resto son variables definidas en Recolección datos WhatsApp</p>
                        </div>

                        <!-- 3. Actualizar custom_fields por teléfono -->
                        <div>
                            <p class="text-xs font-medium uppercase text-[#425b76]">3. Actualizar custom_fields por teléfono</p>
                            <code class="mt-1 block break-all rounded bg-[#e3e8ee] px-3 py-2 text-sm">{{ apiBaseUrl }}/api/agents/{{ agent?.id }}/clients/custom-fields</code>
                            <p class="mt-2 text-xs text-[#425b76]">PATCH — Actualiza únicamente <code class="rounded bg-[#e3e8ee] px-1">custom_fields</code> de un cliente buscándolo por <code class="rounded bg-[#e3e8ee] px-1">phone</code></p>
                            <p class="mt-1 text-xs font-medium text-[#33475b]">Body ejemplo:</p>
                            <pre class="mt-1 overflow-x-auto rounded bg-[#33475b] p-3 text-xs text-[#e3e8ee]">{
  "phone": "573001234567",
  "custom_fields": {
    "edad": "31",
    "ciudad": "Bogotá",
    "origen": "campana_x"
  }
}</pre>
                            <p class="mt-1 text-xs text-[#425b76]">Requeridos: phone y custom_fields (objeto). No modifica name, lastname, email ni otros campos base.</p>
                        </div>

                        <!-- 4. Consultar cliente por teléfono -->
                        <div>
                            <p class="text-xs font-medium uppercase text-[#425b76]">4. Consultar cliente completo por teléfono</p>
                            <code class="mt-1 block break-all rounded bg-[#e3e8ee] px-3 py-2 text-sm">{{ apiClientByPhoneUrl }}</code>
                            <p class="mt-2 text-xs text-[#425b76]">
                                GET o POST — Misma API key del agente. Envía <code class="rounded bg-[#e3e8ee] px-1">phone</code> en query (<code class="rounded bg-[#e3e8ee] px-1">?phone=573001234567</code>) o en el body JSON. Normaliza dígitos y busca como en el panel (exacto o por sufijo).
                            </p>
                            <p class="mt-1 text-xs text-[#425b76]">
                                Respuesta <code class="rounded bg-[#e3e8ee] px-1">200</code>: objeto <code class="rounded bg-[#e3e8ee] px-1">client</code> con datos base, <code class="rounded bg-[#e3e8ee] px-1">custom_fields</code>, <code class="rounded bg-[#e3e8ee] px-1">load_dates</code>, <code class="rounded bg-[#e3e8ee] px-1">contact_logs</code> y <code class="rounded bg-[#e3e8ee] px-1">callback_requests</code> (límites recientes). <code class="rounded bg-[#e3e8ee] px-1">404</code> si no hay cliente para ese teléfono.
                            </p>
                        </div>

                        <!-- 5. Validar existencia por teléfono -->
                        <div>
                            <p class="text-xs font-medium uppercase text-[#425b76]">5. Validar existencia de cliente por teléfono</p>
                            <code class="mt-1 block break-all rounded bg-[#e3e8ee] px-3 py-2 text-sm">{{ apiBaseUrl }}/api/clients/exists-by-phone</code>
                            <p class="mt-2 text-xs text-[#425b76]">POST — Retorna si existe un cliente para ese <code class="rounded bg-[#e3e8ee] px-1">phone</code> filtrando por <code class="rounded bg-[#e3e8ee] px-1">agent_id</code></p>
                            <p class="mt-1 text-xs font-medium text-[#33475b]">Body ejemplo:</p>
                            <pre class="mt-1 overflow-x-auto rounded bg-[#33475b] p-3 text-xs text-[#e3e8ee]">{
  "agent_id": 1,
  "phone": "573001234567"
}</pre>
                            <p class="mt-1 text-xs font-medium text-[#33475b]">Respuesta ejemplo:</p>
                            <pre class="mt-1 overflow-x-auto rounded bg-[#33475b] p-3 text-xs text-[#e3e8ee]">{
  "success": true,
  "exists": true,
  "client_id": 123,
  "name": "Juan Pérez",
  "custom_fields": { "edad": "30", "ciudad": "Bogotá" }
}</pre>
                            <p class="mt-1 text-xs text-[#425b76]">Si no existe, <code class="rounded bg-[#e3e8ee] px-1">exists</code> retorna <code class="rounded bg-[#e3e8ee] px-1">false</code>, <code class="rounded bg-[#e3e8ee] px-1">client_id</code>, <code class="rounded bg-[#e3e8ee] px-1">name</code> y <code class="rounded bg-[#e3e8ee] px-1">custom_fields</code> serán <code class="rounded bg-[#e3e8ee] px-1">null</code>.</p>
                        </div>

                        <!-- 6. Duplicar cliente a otro agente -->
                        <div>
                            <p class="text-xs font-medium uppercase text-[#425b76]">6. Duplicar cliente a otro agente</p>
                            <code class="mt-1 block break-all rounded bg-[#e3e8ee] px-3 py-2 text-sm">{{ apiBaseUrl }}/api/clients/duplicate</code>
                            <p class="mt-2 text-xs text-[#425b76]">POST — Copia datos base (nombre, apellido, email, teléfono, documento) al agente destino; <strong>no</strong> copia <code class="rounded bg-[#e3e8ee] px-1">custom_fields</code> (quedan vacíos en el destino). Mismo propietario. Auth con API key del <strong>agente origen</strong>.</p>
                            <p class="mt-1 text-xs font-medium text-[#33475b]">Body ejemplo:</p>
                            <pre class="mt-1 overflow-x-auto rounded bg-[#33475b] p-3 text-xs text-[#e3e8ee]">{
  "client_id": 123,
  "source_agent_id": 5,
  "target_agent_id": 7
}</pre>
                            <p class="mt-1 text-xs text-[#425b76]">Respuesta 201 con el nuevo <code class="rounded bg-[#e3e8ee] px-1">client</code> en el agente destino. <code class="rounded bg-[#e3e8ee] px-1">403</code> si los agentes no pertenecen al mismo usuario.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
