<script setup>
defineProps({
    closeToast: Function,
    toastProps: Object,
});
</script>

<template>
    <div class="flex flex-col gap-3 p-1">
        <p class="text-sm text-gray-700">{{ toastProps?.data?.message || '¿Realmente deseas iniciar la llamada a este cliente?' }}</p>
        <div class="flex justify-end gap-2">
            <button
                type="button"
                class="rounded border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50"
                @click="() => { toastProps?.data?.onCancel?.(); closeToast?.(); }"
            >
                Cancelar
            </button>
            <button
                type="button"
                class="rounded bg-[var(--color-primary)] px-3 py-1.5 text-sm text-white hover:opacity-90"
                @click="() => { toastProps?.data?.onConfirm?.(); closeToast?.(); }"
            >
                Sí, iniciar llamada
            </button>
        </div>
    </div>
</template>
