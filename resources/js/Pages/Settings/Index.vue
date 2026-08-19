<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    settings: Object,
    messageQuota: Object,
    transactions: Array,
    alertCategories: { type: Array, default: () => [] },
});

const logoPreview = ref(null);
const fileInput = ref(null);
const faviconPreview = ref(null);
const faviconInput = ref(null);

const form = useForm({
    company_name: props.settings?.company_name || '',
    contact_name: props.settings?.contact_name || '',
    contact_email: props.settings?.contact_email || '',
    company_logo: null,
    site_favicon: null,
    payment_method: props.settings?.payment_method || '',
    message_cost: props.settings?.message_cost ?? '',
    call_minute_cost: props.settings?.call_minute_cost ?? '',
    monthly_message_cap: props.settings?.monthly_message_cap ?? '',
    primary_color: props.settings?.primary_color || '#a3e635',
    whatsapp_conversations_source: props.settings?.whatsapp_conversations_source || 'internal',
    whatsapp_conversations_supabase_table_pattern: props.settings?.whatsapp_conversations_supabase_table_pattern || '',
    whatsapp_conversations_supabase_phone_column: props.settings?.whatsapp_conversations_supabase_phone_column || '',
    whatsapp_conversations_supabase_date_column: props.settings?.whatsapp_conversations_supabase_date_column || '',
    call_transcripts_source: props.settings?.call_transcripts_source || 'internal',
    call_alert_emails: Array.isArray(props.settings?.call_alert_emails) ? [...props.settings.call_alert_emails] : [],
});

/* ---- Correos de alerta de llamada ---- */
const newAlertEmail = ref('');
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function addAlertEmail() {
    const email = newAlertEmail.value.trim().toLowerCase();
    if (!email || !emailRegex.test(email)) return;
    if (!form.call_alert_emails.includes(email)) form.call_alert_emails.push(email);
    newAlertEmail.value = '';
}

function removeAlertEmail(email) {
    form.call_alert_emails = form.call_alert_emails.filter((e) => e !== email);
}

/* ---- Categorías de alerta de llamada (CRUD) ---- */
const newCategory = ref({ nombre: '', color: '#a3e635' });
const editingCategory = ref(null);

function addCategory() {
    if (!newCategory.value.nombre.trim()) return;
    router.post(route('settings.alert-categories.store'), {
        nombre: newCategory.value.nombre.trim(),
        color: newCategory.value.color || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { newCategory.value = { nombre: '', color: '#a3e635' }; },
    });
}

function startEditCategory(cat) {
    editingCategory.value = { id: cat.id, nombre: cat.nombre, color: cat.color || '#a3e635' };
}

function cancelEditCategory() {
    editingCategory.value = null;
}

function saveCategory() {
    const c = editingCategory.value;
    if (!c || !c.nombre.trim()) return;
    router.put(route('settings.alert-categories.update', c.id), {
        nombre: c.nombre.trim(),
        color: c.color || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { editingCategory.value = null; },
    });
}

function deleteCategory(cat) {
    if (!confirm(`¿Eliminar la categoría "${cat.nombre}"? Las alertas asociadas quedarán sin categoría.`)) return;
    router.delete(route('settings.alert-categories.destroy', cat.id), { preserveScroll: true });
}

function onLogoChange(e) {
    const file = e.target.files?.[0];
    if (file) {
        form.company_logo = file;
        const reader = new FileReader();
        reader.onload = (ev) => (logoPreview.value = ev.target?.result);
        reader.readAsDataURL(file);
    }
}

function removeLogo() {
    form.company_logo = null;
    logoPreview.value = null;
    if (fileInput.value) fileInput.value.value = '';
    router.delete(route('settings.remove-logo'));
}

function onFaviconChange(e) {
    const file = e.target.files?.[0];
    if (file) {
        form.site_favicon = file;
        const reader = new FileReader();
        reader.onload = (ev) => (faviconPreview.value = ev.target?.result);
        reader.readAsDataURL(file);
    }
}

function removeFavicon() {
    form.site_favicon = null;
    faviconPreview.value = null;
    if (faviconInput.value) faviconInput.value.value = '';
    router.delete(route('settings.remove-favicon'));
}

function submit() {
    form.transform((data) => ({
        ...data,
        company_logo: data.company_logo || undefined,
        site_favicon: data.site_favicon || undefined,
    })).put(route('settings.update'), {
        forceFormData: true,
    });
}

const currentLogoUrl = props.settings?.company_logo
    ? `/storage/${props.settings.company_logo}`
    : '/colsanitas.png';

const currentFaviconUrl = props.settings?.site_favicon
    ? `/storage/${props.settings.site_favicon}`
    : '/colsanitas.png';

const apiBaseUrl = computed(() => (typeof window !== 'undefined' ? window.location.origin : ''));
const showApiDocs = ref(false);

const quotaBarWidth = computed(() => {
    const pct = props.messageQuota?.percent ?? 0;
    return `${Math.min(100, Math.max(0, pct))}%`;
});

const quotaBarColor = computed(() => {
    const pct = props.messageQuota?.percent ?? 0;
    if (pct >= 100) return 'bg-red-500';
    if (pct >= 80) return 'bg-orange-500';
    if (pct >= 50) return 'bg-yellow-500';
    return 'bg-[var(--color-primary)]';
});
</script>

<template>
    <Head title="Configuración general" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-[#33475b]">
                Configuración general
            </h2>
        </template>

        <div class="py-8">
            <div class="w-full space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Empresa y logo -->
                <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-[#33475b]">Empresa</h3>
                        <p class="mt-1 text-sm text-[#425b76]">
                            Nombre, logo y favicon que se mostrarán en el header, login y pestaña del navegador.
                        </p>

                        <form @submit.prevent="submit" class="mt-6 space-y-6">
                            <div>
                                <InputLabel for="company_name" value="Nombre de la empresa" />
                                <TextInput
                                    id="company_name"
                                    v-model="form.company_name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="Ej: Mi Empresa"
                                />
                                <InputError :message="form.errors.company_name" />
                            </div>

                            <div>
                                <p class="text-sm font-medium text-[#33475b]">Información de contacto</p>
                                <p class="mt-1 text-xs text-[#425b76]">
                                    Se utilizará para enviar reportes mensuales de consumos al correo.
                                </p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel for="contact_name" value="Nombre completo" />
                                    <TextInput
                                        id="contact_name"
                                        v-model="form.contact_name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        placeholder="Ej: Juan Pérez"
                                    />
                                    <InputError :message="form.errors.contact_name" />
                                </div>
                                <div>
                                    <InputLabel for="contact_email" value="Email" />
                                    <TextInput
                                        id="contact_email"
                                        v-model="form.contact_email"
                                        type="email"
                                        class="mt-1 block w-full"
                                        placeholder="Ej: contacto@empresa.com"
                                    />
                                    <InputError :message="form.errors.contact_email" />
                                </div>
                            </div>

                            <div>
                                <InputLabel value="Logo" />
                                <div class="mt-2 flex items-center gap-4">
                                    <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[#e3e8ee] bg-[#f5f8fa]">
                                        <img
                                            v-if="logoPreview"
                                            :src="logoPreview"
                                            alt="Vista previa"
                                            class="h-full w-full object-contain"
                                        />
                                        <img
                                            v-else
                                            :src="currentLogoUrl"
                                            alt="Logo actual"
                                            class="h-full w-full object-contain"
                                        />
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <input
                                            ref="fileInput"
                                            type="file"
                                            accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                            class="block w-full text-sm text-[#425b76] file:mr-4 file:rounded-md file:border-0 file:bg-[var(--color-primary)] file:px-4 file:py-2 file:text-sm file:font-medium file:text-[var(--color-primary-foreground)] file:hover:bg-[var(--color-primary-hover)]"
                                            @change="onLogoChange"
                                        />
                                        <button
                                            v-if="settings?.company_logo || form.company_logo"
                                            type="button"
                                            class="text-left text-sm text-red-600 hover:text-red-800"
                                            @click="removeLogo"
                                        >
                                            Eliminar logo
                                        </button>
                                    </div>
                                </div>
                                <p class="mt-1 text-xs text-[#425b76]">
                                    PNG, JPG, GIF o WebP. Máx. 2 MB.
                                </p>
                                <InputError :message="form.errors.company_logo" />
                            </div>

                            <div>
                                <InputLabel value="Favicon" />
                                <div class="mt-2 flex items-center gap-4">
                                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[#e3e8ee] bg-[#f5f8fa]">
                                        <img
                                            v-if="faviconPreview"
                                            :src="faviconPreview"
                                            alt="Vista previa favicon"
                                            class="h-full w-full object-contain"
                                        />
                                        <img
                                            v-else
                                            :src="currentFaviconUrl"
                                            alt="Favicon actual"
                                            class="h-full w-full object-contain"
                                        />
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <input
                                            ref="faviconInput"
                                            type="file"
                                            accept="image/jpeg,image/png,image/jpg,image/gif,image/webp,image/svg+xml,.ico"
                                            class="block w-full text-sm text-[#425b76] file:mr-4 file:rounded-md file:border-0 file:bg-[var(--color-primary)] file:px-4 file:py-2 file:text-sm file:font-medium file:text-[var(--color-primary-foreground)] file:hover:bg-[var(--color-primary-hover)]"
                                            @change="onFaviconChange"
                                        />
                                        <button
                                            v-if="settings?.site_favicon || form.site_favicon"
                                            type="button"
                                            class="text-left text-sm text-red-600 hover:text-red-800"
                                            @click="removeFavicon"
                                        >
                                            Quitar favicon personalizado
                                        </button>
                                    </div>
                                </div>
                                <p class="mt-1 text-xs text-[#425b76]">
                                    Icono de la pestaña del navegador. PNG, JPG, GIF, WebP, SVG o ICO. Máx. 2 MB.
                                </p>
                                <InputError :message="form.errors.site_favicon" />
                            </div>

                            <div>
                                <InputLabel for="primary_color" value="Color principal de la plataforma" />
                                <div class="mt-2 flex items-center gap-3">
                                    <input
                                        id="primary_color"
                                        v-model="form.primary_color"
                                        type="color"
                                        class="h-10 w-16 cursor-pointer rounded border border-[#e3e8ee] bg-white p-1"
                                    />
                                    <TextInput
                                        v-model="form.primary_color"
                                        type="text"
                                        class="mt-0 block w-32 font-mono text-sm"
                                        placeholder="#a3e635"
                                    />
                                </div>
                                <p class="mt-1 text-xs text-[#425b76]">
                                    Color que se aplica a botones, enlaces y acentos en toda la plataforma.
                                </p>
                                <InputError :message="form.errors.primary_color" />
                            </div>

                            <div>
                                <InputLabel for="whatsapp_conversations_source" value="Origen de conversaciones WhatsApp (solo lectura)" />
                                <select
                                    id="whatsapp_conversations_source"
                                    v-model="form.whatsapp_conversations_source"
                                    class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)] sm:max-w-md"
                                >
                                    <option value="internal">Base de datos de la aplicación (variables DB_* en .env)</option>
                                    <option value="supabase">Supabase (solo lectura; las tablas no se crean desde aquí)</option>
                                </select>
                                <p class="mt-1 text-xs text-[#425b76]">
                                    Solo se <strong>leen</strong> mensajes (modal del cliente y totales del dashboard).
                                    Con <strong>BD interna</strong>, al crear un agente la app crea una tabla por agente (prefijo <code class="rounded bg-[#e3e8ee] px-1 font-mono text-[11px]">agent_</code> + id + <code class="rounded bg-[#e3e8ee] px-1 font-mono text-[11px]">_whatsapp_conversations</code>). Con <strong>Supabase</strong>, defines el patrón de nombre abajo; la app lee por <strong>API REST</strong> con <code class="rounded bg-[#e3e8ee] px-1">VITE_SUPABASE_URL</code> y <code class="rounded bg-[#e3e8ee] px-1">VITE_SUPABASE_ANON_KEY</code>.
                                </p>
                                <InputError :message="form.errors.whatsapp_conversations_source" />
                            </div>

                            <div v-if="form.whatsapp_conversations_source === 'supabase'">
                                <InputLabel for="whatsapp_conversations_supabase_table_pattern" value="Patrón de tabla en Supabase (WhatsApp)" />
                                <TextInput
                                    id="whatsapp_conversations_supabase_table_pattern"
                                    v-model="form.whatsapp_conversations_supabase_table_pattern"
                                    type="text"
                                    class="mt-1 block w-full font-mono text-sm sm:max-w-xl"
                                    placeholder="agent_{agent_id}_whatsapp_conversations"
                                    autocomplete="off"
                                />
                                <p class="mt-1 text-xs text-[#425b76]">
                                    Debe incluir el marcador <code class="rounded bg-[#e3e8ee] px-1">{agent_id}</code> (se reemplaza por el ID del agente). Ejemplo: <code class="rounded bg-[#e3e8ee] px-1 font-mono text-[11px]">mi_prefijo_{agent_id}_wa</code> → para el agente 12 la tabla es <code class="rounded bg-[#e3e8ee] px-1 font-mono text-[11px]">mi_prefijo_12_wa</code>. Vacío = valor por defecto del servidor (<code class="rounded bg-[#e3e8ee] px-1 font-mono text-[11px]">agent_{agent_id}_whatsapp_conversations</code> o <code class="rounded bg-[#e3e8ee] px-1">WHATSAPP_SUPABASE_TABLE_PATTERN</code> en <code class="rounded bg-[#e3e8ee] px-1">.env</code>).
                                </p>
                                <InputError :message="form.errors.whatsapp_conversations_supabase_table_pattern" />
                            </div>

                            <div v-if="form.whatsapp_conversations_source === 'supabase'">
                                <InputLabel for="whatsapp_conversations_supabase_phone_column" value="Columna del teléfono en la tabla (Supabase)" />
                                <TextInput
                                    id="whatsapp_conversations_supabase_phone_column"
                                    v-model="form.whatsapp_conversations_supabase_phone_column"
                                    type="text"
                                    class="mt-1 block w-full font-mono text-sm sm:max-w-md"
                                    placeholder="session_id"
                                    autocomplete="off"
                                />
                                <p class="mt-1 text-xs text-[#425b76]">
                                    Columna PostgREST del número (solo dígitos en el filtro). Muchas tablas usan <code class="rounded bg-[#e3e8ee] px-1">session_id</code>; otras <code class="rounded bg-[#e3e8ee] px-1">phone</code>. Vacío = <code class="rounded bg-[#e3e8ee] px-1">WHATSAPP_SUPABASE_PHONE_COLUMN</code> en <code class="rounded bg-[#e3e8ee] px-1">.env</code> (por defecto <code class="rounded bg-[#e3e8ee] px-1">session_id</code>).
                                </p>
                                <InputError :message="form.errors.whatsapp_conversations_supabase_phone_column" />
                            </div>

                            <div v-if="form.whatsapp_conversations_source === 'supabase'">
                                <InputLabel for="whatsapp_conversations_supabase_date_column" value="Columna de fecha para el tope mensual (Supabase)" />
                                <TextInput
                                    id="whatsapp_conversations_supabase_date_column"
                                    v-model="form.whatsapp_conversations_supabase_date_column"
                                    type="text"
                                    class="mt-1 block w-full font-mono text-sm sm:max-w-md"
                                    placeholder="created_at"
                                    autocomplete="off"
                                />
                                <p class="mt-1 text-xs text-[#425b76]">
                                    Columna de fecha/hora usada para contar solo los mensajes del <strong>mes en curso</strong> (tope mensual). Debe existir en la tabla. Vacío = <code class="rounded bg-[#e3e8ee] px-1">created_at</code> (o <code class="rounded bg-[#e3e8ee] px-1">WHATSAPP_SUPABASE_DATE_COLUMN</code> en <code class="rounded bg-[#e3e8ee] px-1">.env</code>).
                                </p>
                                <InputError :message="form.errors.whatsapp_conversations_supabase_date_column" />
                            </div>

                            <div>
                                <InputLabel for="call_transcripts_source" value="Origen de transcripciones de llamadas (solo lectura)" />
                                <select
                                    id="call_transcripts_source"
                                    v-model="form.call_transcripts_source"
                                    class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)] sm:max-w-md"
                                >
                                    <option value="internal">Base de datos de la aplicación (DB_*)</option>
                                    <option value="supabase">Supabase (solo lectura)</option>
                                </select>
                                <p class="mt-1 text-xs text-[#425b76]">
                                    Define desde dónde se leen transcripciones (cliente y dashboard). Con Supabase se usa la <strong>API REST</strong> (<code class="rounded bg-[#e3e8ee] px-1">VITE_SUPABASE_URL</code> + anon key). Tabla/campaña: <code class="rounded bg-[#e3e8ee] px-1 font-mono text-[11px]">CAMPANA_AINOA</code>, etc. Solo lectura.
                                </p>
                                <InputError :message="form.errors.call_transcripts_source" />
                            </div>

                            <div>
                                <InputLabel for="payment_method" value="Método de pago" />
                                <TextInput
                                    id="payment_method"
                                    v-model="form.payment_method"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="Ej: Tarjeta de crédito, Transferencia"
                                />
                                <InputError :message="form.errors.payment_method" />
                            </div>

                            <div>
                                <p class="text-sm font-medium text-[#33475b]">Costos (USD)</p>
                                <p class="mt-1 text-xs text-[#425b76]">
                                    Valores para calcular consumos en reportes mensuales.
                                </p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel for="message_cost" value="Costo por mensaje (USD)" />
                                    <TextInput
                                        id="message_cost"
                                        v-model="form.message_cost"
                                        type="number"
                                        step="0.001"
                                        min="0"
                                        class="mt-1 block w-full"
                                        placeholder="Ej: 0.005"
                                    />
                                    <InputError :message="form.errors.message_cost" />
                                </div>
                                <div>
                                    <InputLabel for="call_minute_cost" value="Costo por minuto de llamada (USD)" />
                                    <TextInput
                                        id="call_minute_cost"
                                        v-model="form.call_minute_cost"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="mt-1 block w-full"
                                        placeholder="Ej: 0.10"
                                    />
                                    <InputError :message="form.errors.call_minute_cost" />
                                </div>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-[#33475b]">Tope de mensajes al mes</p>
                                <p class="mt-1 text-xs text-[#425b76]">
                                    Límite de mensajes de WhatsApp por mes calendario. Se avisa al correo de contacto al llegar al
                                    <strong>50%</strong>, <strong>80%</strong> y <strong>100%</strong> del tope (un aviso por umbral, cada mes).
                                    El consumo se cuenta desde la tabla de Supabase para que sea preciso. Deja vacío o 0 para desactivar.
                                </p>
                            </div>
                            <div>
                                <InputLabel for="monthly_message_cap" value="Tope mensual de mensajes" />
                                <TextInput
                                    id="monthly_message_cap"
                                    v-model="form.monthly_message_cap"
                                    type="number"
                                    step="1"
                                    min="0"
                                    class="mt-1 block w-full sm:max-w-xs"
                                    placeholder="Ej: 10000"
                                />
                                <InputError :message="form.errors.monthly_message_cap" />

                                <div v-if="messageQuota" class="mt-3 rounded-lg border border-[#e3e8ee] bg-[#f5f8fa] p-4 sm:max-w-md">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="font-medium text-[#33475b]">Consumo del mes ({{ messageQuota.month }})</span>
                                        <span v-if="messageQuota.used !== null" class="font-semibold text-[#33475b]">
                                            {{ Number(messageQuota.used).toLocaleString() }} / {{ Number(messageQuota.cap).toLocaleString() }}
                                        </span>
                                        <span v-else class="text-xs text-[#425b76]">No disponible</span>
                                    </div>
                                    <template v-if="messageQuota.percent !== null">
                                        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-[#e3e8ee]">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="quotaBarColor"
                                                :style="{ width: quotaBarWidth }"
                                            />
                                        </div>
                                        <p class="mt-1 text-xs text-[#425b76]">
                                            {{ messageQuota.percent }}% del tope mensual.
                                        </p>
                                    </template>
                                    <p v-else class="mt-2 text-xs text-[#425b76]">
                                        No se pudo leer el consumo desde Supabase. Revisa el origen, la tabla y la columna de fecha.
                                    </p>
                                </div>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-[#33475b]">Correos de alerta de llamada</p>
                                <p class="mt-1 text-xs text-[#425b76]">
                                    Uno o varios correos que recibirán una notificación cada vez que llegue una nueva
                                    alerta de llamada. Escribe un correo y presiona Enter o «Agregar».
                                </p>
                                <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:max-w-md">
                                    <TextInput
                                        v-model="newAlertEmail"
                                        type="email"
                                        class="block w-full"
                                        placeholder="Ej: alertas@empresa.com"
                                        @keydown.enter.prevent="addAlertEmail"
                                    />
                                    <button
                                        type="button"
                                        class="shrink-0 rounded-md border border-[#e3e8ee] bg-[#f5f8fa] px-3 py-2 text-sm font-medium text-[#33475b] transition hover:bg-[#eef2f6]"
                                        @click="addAlertEmail"
                                    >
                                        Agregar
                                    </button>
                                </div>
                                <div v-if="form.call_alert_emails.length" class="mt-3 flex flex-wrap gap-2">
                                    <span
                                        v-for="email in form.call_alert_emails"
                                        :key="email"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-[#eef2f6] py-1 pl-3 pr-1.5 text-sm text-[#33475b]"
                                    >
                                        {{ email }}
                                        <button
                                            type="button"
                                            class="rounded-full p-0.5 text-[#98a4b3] hover:bg-red-50 hover:text-red-500"
                                            @click="removeAlertEmail(email)"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    </span>
                                </div>
                                <p v-else class="mt-2 text-xs text-[#98a4b3]">No hay correos configurados.</p>
                                <InputError :message="form.errors.call_alert_emails" />
                            </div>

                            <div class="flex items-center gap-4">
                                <PrimaryButton :disabled="form.processing">
                                    Guardar configuración
                                </PrimaryButton>
                                <Transition
                                    enter-active-class="transition ease-in-out"
                                    enter-from-class="opacity-0"
                                    leave-active-class="transition ease-in-out"
                                    leave-to-class="opacity-0"
                                >
                                    <p v-if="form.recentlySuccessful" class="text-sm text-[#425b76]">
                                        Guardado.
                                    </p>
                                </Transition>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Categorías de alerta de llamada -->
                <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-[#33475b]">Categorías de alerta de llamada</h3>
                        <p class="mt-1 text-sm text-[#425b76]">
                            Clasificaciones disponibles para las alertas de llamada (p. ej. Problema, Alerta).
                            Puedes agregar, editar o eliminar las que necesites.
                        </p>

                        <!-- Lista de categorías -->
                        <ul class="mt-6 divide-y divide-[#eef2f6] rounded-lg border border-[#eef2f6]">
                            <li v-if="!alertCategories.length" class="px-4 py-4 text-sm text-[#98a4b3]">
                                Aún no hay categorías.
                            </li>
                            <li v-for="cat in alertCategories" :key="cat.id" class="px-4 py-3">
                                <!-- Modo edición -->
                                <div v-if="editingCategory && editingCategory.id === cat.id" class="flex flex-wrap items-center gap-2">
                                    <input
                                        v-model="editingCategory.color"
                                        type="color"
                                        class="h-9 w-10 shrink-0 cursor-pointer rounded border border-[#e3e8ee] bg-white p-0.5"
                                    />
                                    <TextInput v-model="editingCategory.nombre" type="text" class="min-w-0 flex-1" @keydown.enter.prevent="saveCategory" />
                                    <button
                                        type="button"
                                        class="rounded-md bg-[var(--color-primary)] px-3 py-2 text-sm font-medium text-[var(--color-primary-foreground)] transition hover:bg-[var(--color-primary-hover)]"
                                        @click="saveCategory"
                                    >
                                        Guardar
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border border-[#e3e8ee] px-3 py-2 text-sm text-[#425b76] transition hover:bg-[#eef2f6]"
                                        @click="cancelEditCategory"
                                    >
                                        Cancelar
                                    </button>
                                </div>
                                <!-- Modo lectura -->
                                <div v-else class="flex items-center justify-between gap-2">
                                    <span class="flex items-center gap-2 text-sm text-[#33475b]">
                                        <span class="inline-block h-3 w-3 shrink-0 rounded-full border border-[#e3e8ee]" :style="{ backgroundColor: cat.color || '#c2cddb' }" />
                                        {{ cat.nombre }}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            class="rounded p-1.5 text-[#425b76] hover:bg-[#eef2f6]"
                                            title="Editar"
                                            @click="startEditCategory(cat)"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded p-1.5 text-[#c2cddb] hover:bg-red-50 hover:text-red-500"
                                            title="Eliminar"
                                            @click="deleteCategory(cat)"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </span>
                                </div>
                            </li>
                        </ul>

                        <!-- Agregar categoría -->
                        <div class="mt-4 flex flex-wrap items-end gap-2">
                            <div>
                                <InputLabel value="Color" />
                                <input
                                    v-model="newCategory.color"
                                    type="color"
                                    class="mt-1 h-10 w-12 cursor-pointer rounded border border-[#e3e8ee] bg-white p-0.5"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <InputLabel for="new_category_nombre" value="Nueva categoría" />
                                <TextInput
                                    id="new_category_nombre"
                                    v-model="newCategory.nombre"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="Ej: Urgente"
                                    @keydown.enter.prevent="addCategory"
                                />
                            </div>
                            <button
                                type="button"
                                :disabled="!newCategory.nombre.trim()"
                                class="rounded-md bg-[var(--color-primary)] px-4 py-2 text-sm font-medium text-[var(--color-primary-foreground)] transition hover:bg-[var(--color-primary-hover)] disabled:opacity-50"
                                @click="addCategory"
                            >
                                Agregar categoría
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Documentación API (global) -->
                <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-[#33475b]">API REST — referencia</h3>
                        <p class="mt-1 text-sm text-[#425b76]">
                            Uso de los endpoints públicos por agente. Las <strong>API keys</strong> se generan en cada agente:
                            <strong>Agente → Configuración → API Keys</strong>. Sustituye <code class="rounded bg-[#e3e8ee] px-1 font-mono text-xs">{agent_id}</code> por el ID numérico del agente.
                        </p>
                        <p class="mt-2 text-sm text-[#33475b]">
                            <span class="font-medium">Autenticación:</span>
                            header <code class="rounded bg-[#e3e8ee] px-1 text-xs">Authorization: Bearer TU_API_KEY</code>
                            o <code class="rounded bg-[#e3e8ee] px-1 text-xs">X-Api-Key: TU_API_KEY</code>
                            (la clave corresponde al agente indicado en la ruta o al agente origen en duplicados / validaciones).
                        </p>

                        <button
                            type="button"
                            class="mt-4 flex items-center gap-2 text-sm font-medium text-[#425b76] hover:text-[#33475b]"
                            @click="showApiDocs = !showApiDocs"
                        >
                            {{ showApiDocs ? 'Ocultar' : 'Ver' }} detalle de endpoints
                            <svg
                                :class="['h-4 w-4 transition', showApiDocs ? 'rotate-180' : '']"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div v-show="showApiDocs" class="mt-4 space-y-6 border-t border-[#e3e8ee] pt-6">
                            <!-- 1 -->
                            <div>
                                <p class="text-xs font-medium uppercase text-[#425b76]">1. Crear o actualizar cliente</p>
                                <code class="mt-1 block break-all rounded bg-[#e3e8ee] px-3 py-2 text-sm">{{ apiBaseUrl }}/api/agents/{agent_id}/clients</code>
                                <p class="mt-2 text-xs text-[#425b76]">POST — Crea o actualiza por documento o teléfono.</p>
                                <pre class="mt-2 overflow-x-auto rounded bg-[#33475b] p-3 text-xs text-[#e3e8ee]">{
  "name": "Juan",
  "lastname": "Pérez",
  "email": "juan@ejemplo.com",
  "phone": "573001234567",
  "document_type": "CC",
  "document": "123456789",
  "custom_fields": { "edad": "30" }
}</pre>
                            </div>
                            <!-- 2 -->
                            <div>
                                <p class="text-xs font-medium uppercase text-[#425b76]">2. Recolección datos WhatsApp</p>
                                <code class="mt-1 block break-all rounded bg-[#e3e8ee] px-3 py-2 text-sm">{{ apiBaseUrl }}/api/agents/{agent_id}/collect-data</code>
                                <p class="mt-2 text-xs text-[#425b76]">POST — Requiere <code class="rounded bg-[#e3e8ee] px-1">phone</code> (solo dígitos).</p>
                            </div>
                            <!-- 3 -->
                            <div>
                                <p class="text-xs font-medium uppercase text-[#425b76]">3. Actualizar solo custom_fields por teléfono</p>
                                <code class="mt-1 block break-all rounded bg-[#e3e8ee] px-3 py-2 text-sm">{{ apiBaseUrl }}/api/agents/{agent_id}/clients/custom-fields</code>
                                <p class="mt-2 text-xs text-[#425b76]">PATCH — Body: <code class="rounded bg-[#e3e8ee] px-1">phone</code> + <code class="rounded bg-[#e3e8ee] px-1">custom_fields</code>.</p>
                            </div>
                            <!-- 4 -->
                            <div>
                                <p class="text-xs font-medium uppercase text-[#425b76]">4. Consultar cliente completo por teléfono</p>
                                <code class="mt-1 block break-all rounded bg-[#e3e8ee] px-3 py-2 text-sm">{{ apiBaseUrl }}/api/agents/{agent_id}/clients/by-phone</code>
                                <p class="mt-2 text-xs text-[#425b76]">
                                    GET o POST — Parámetro <code class="rounded bg-[#e3e8ee] px-1">phone</code> en query (<code class="rounded bg-[#e3e8ee] px-1">?phone=</code>) o en el body JSON. Devuelve el registro del cliente del agente con campos base, <code class="rounded bg-[#e3e8ee] px-1">custom_fields</code>, últimas cargas (<code class="rounded bg-[#e3e8ee] px-1">load_dates</code>), contactos (<code class="rounded bg-[#e3e8ee] px-1">contact_logs</code>) y callbacks (<code class="rounded bg-[#e3e8ee] px-1">callback_requests</code>). <code class="rounded bg-[#e3e8ee] px-1">404</code> si no existe.
                                </p>
                            </div>
                            <!-- 5 -->
                            <div>
                                <p class="text-xs font-medium uppercase text-[#425b76]">5. ¿Existe cliente por teléfono?</p>
                                <code class="mt-1 block break-all rounded bg-[#e3e8ee] px-3 py-2 text-sm">{{ apiBaseUrl }}/api/clients/exists-by-phone</code>
                                <p class="mt-2 text-xs text-[#425b76]">POST — Body: <code class="rounded bg-[#e3e8ee] px-1">agent_id</code>, <code class="rounded bg-[#e3e8ee] px-1">phone</code>. API key del agente del <code class="rounded bg-[#e3e8ee] px-1">agent_id</code>.</p>
                                <p class="mt-1 text-xs text-[#425b76]">Respuesta incluye <code class="rounded bg-[#e3e8ee] px-1">exists</code>, <code class="rounded bg-[#e3e8ee] px-1">name</code> y <code class="rounded bg-[#e3e8ee] px-1">custom_fields</code> si existe.</p>
                            </div>
                            <!-- 6 -->
                            <div>
                                <p class="text-xs font-medium uppercase text-[#425b76]">6. Duplicar cliente a otro agente</p>
                                <code class="mt-1 block break-all rounded bg-[#e3e8ee] px-3 py-2 text-sm">{{ apiBaseUrl }}/api/clients/duplicate</code>
                                <p class="mt-2 text-xs text-[#425b76]">
                                    POST — Copia campos base del cliente al destino; <strong>no</strong> copia <code class="rounded bg-[#e3e8ee] px-1">custom_fields</code>.
                                    Usa la API key del <strong>agente origen</strong> (<code class="rounded bg-[#e3e8ee] px-1">source_agent_id</code>).
                                    Ambos agentes deben pertenecer al mismo usuario.
                                </p>
                                <p class="mt-1 text-xs font-medium text-[#33475b]">Body ejemplo:</p>
                                <pre class="mt-1 overflow-x-auto rounded bg-[#33475b] p-3 text-xs text-[#e3e8ee]">{
  "client_id": 123,
  "source_agent_id": 5,
  "target_agent_id": 7
}</pre>
                                <p class="mt-1 text-xs text-[#425b76]">
                                    Respuesta <code class="rounded bg-[#e3e8ee] px-1">201</code> con el nuevo cliente en el agente destino.
                                    <code class="rounded bg-[#e3e8ee] px-1">403</code> si los agentes no pertenecen al mismo usuario.
                                </p>
                            </div>
                            <!-- 7 -->
                            <div>
                                <p class="text-xs font-medium uppercase text-[#425b76]">7. Registrar alerta de llamada</p>
                                <code class="mt-1 block break-all rounded bg-[#e3e8ee] px-3 py-2 text-sm">{{ apiBaseUrl }}/api/agents/{agent_id}/call-alerts</code>
                                <p class="mt-2 text-xs text-[#425b76]">
                                    POST — Requiere <code class="rounded bg-[#e3e8ee] px-1">phone</code>. Vincula la alerta al cliente con ese
                                    teléfono (si existe) y notifica por correo a los destinatarios configurados arriba.
                                    <code class="rounded bg-[#e3e8ee] px-1">categoria</code> es opcional (por nombre, p. ej. «Problema»); si no coincide, la alerta queda sin categoría.
                                </p>
                                <pre class="mt-2 overflow-x-auto rounded bg-[#33475b] p-3 text-xs text-[#e3e8ee]">{
  "phone": "573001234567",
  "numero_llamada": 3,
  "paso_llamada": 2,
  "descripcion": "El cliente reporta un problema con el pago",
  "categoria": "Problema"
}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Historial de transacciones -->
                <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-[#33475b]">Historial de transacciones</h3>
                        <p class="mt-1 text-sm text-[#425b76]">
                            Últimas transacciones registradas.
                        </p>

                        <div v-if="transactions?.length" class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-[#e3e8ee]">
                                <thead class="bg-[#f5f8fa]">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#425b76]">Fecha</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#425b76]">Descripción</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#425b76]">Método</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-[#425b76]">Monto</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#425b76]">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e3e8ee] bg-white">
                                    <tr v-for="tx in transactions" :key="tx.id" class="hover:bg-[#f5f8fa]">
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-[#33475b]">
                                            {{ new Date(tx.transacted_at).toLocaleDateString() }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-[#33475b]">{{ tx.description }}</td>
                                        <td class="px-4 py-3 text-sm text-[#425b76]">{{ tx.payment_method || '-' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-[#33475b]">
                                            {{ tx.currency }} {{ Number(tx.amount).toLocaleString() }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                :class="[
                                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                                    tx.status === 'completed' ? 'bg-green-100 text-green-800' : '',
                                                    tx.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '',
                                                    tx.status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'
                                                ]"
                                            >
                                                {{ tx.status }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="mt-6 rounded-lg border border-dashed border-[#e3e8ee] py-12 text-center text-[#425b76]">
                            No hay transacciones registradas.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
