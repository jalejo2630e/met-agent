<script setup>
import { ref, watch } from 'vue';
import ClientCallsPanel from '@/Components/ClientCallsPanel.vue';
import ClientWhatsappPanel from '@/Components/ClientWhatsappPanel.vue';

/**
 * Modal único de contacto del cliente con dos pestañas: Llamadas y WhatsApp.
 * Reutilizable por el listado de clientes y la pestaña Clientes del agente.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    agent: { type: Object, required: true },
    client: { type: Object, default: null },
    plantillas: { type: Array, default: () => [] },
    defaultPlantillaId: { type: String, default: '' },
    initialTab: { type: String, default: 'calls' },
});

const emit = defineEmits(['close']);

const activeTab = ref(props.initialTab);
watch(() => props.show, (open) => {
    if (open) activeTab.value = props.initialTab || 'calls';
});
</script>

<template>
    <div v-show="show" class="fixed inset-0 z-50 overflow-y-auto" @keydown.esc="emit('close')">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="emit('close')" />
            <div class="relative flex h-[85vh] w-full max-w-4xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-[#e3e8ee] px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-900">
                        {{ client ? `${client.name} ${client.lastname}` : 'Contacto' }}
                    </h3>
                    <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" @click="emit('close')">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Pestañas -->
                <div class="flex gap-1 border-b border-[#e3e8ee] px-4">
                    <button
                        type="button"
                        :class="['px-4 py-2 text-sm font-medium transition', activeTab === 'calls' ? 'border-b-2 border-[#1976d2] text-[#1976d2]' : 'text-gray-500 hover:text-gray-700']"
                        @click="activeTab = 'calls'"
                    >
                        Llamadas
                    </button>
                    <button
                        type="button"
                        :class="['px-4 py-2 text-sm font-medium transition', activeTab === 'whatsapp' ? 'border-b-2 border-[#25D366] text-[#128C7E]' : 'text-gray-500 hover:text-gray-700']"
                        @click="activeTab = 'whatsapp'"
                    >
                        WhatsApp
                    </button>
                </div>

                <!-- Contenido -->
                <div class="min-h-0 flex-1 overflow-hidden">
                    <div v-show="activeTab === 'calls'" class="h-full">
                        <ClientCallsPanel :agent="agent" :client="client" :active="show && activeTab === 'calls'" />
                    </div>
                    <div v-show="activeTab === 'whatsapp'" class="h-full overflow-y-auto">
                        <ClientWhatsappPanel
                            :agent="agent"
                            :client="client"
                            :plantillas="plantillas"
                            :default-plantilla-id="defaultPlantillaId"
                            :active="show && activeTab === 'whatsapp'"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
