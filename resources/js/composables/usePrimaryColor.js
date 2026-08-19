import { watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const DEFAULT_PRIMARY = '#a3e635';

/**
 * Aplica el color principal de la plataforma (configuración) a las variables CSS
 * para que toda la UI que usa var(--color-primary) refleje el valor elegido.
 * Debe usarse en layouts/páginas raíz para que se actualice al guardar configuración.
 */
export function usePrimaryColor() {
    const page = usePage();

    function applyPrimaryColor() {
        const hex = page.props.settings?.primary_color ?? DEFAULT_PRIMARY;
        if (hex && /^#[0-9A-Fa-f]{6}$/.test(hex)) {
            document.documentElement.style.setProperty('--color-primary', hex);
        }
    }

    onMounted(applyPrimaryColor);
    watch(
        () => page.props.settings?.primary_color,
        () => applyPrimaryColor(),
        { immediate: false }
    );
}
