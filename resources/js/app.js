import './bootstrap';
import 'vue3-toastify/dist/index.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import Vue3Toastify, { toast } from 'vue3-toastify';

const appName = import.meta.env.VITE_APP_NAME || 'MET';

// Sesión expirada: cuando el servidor responde algo que no es una respuesta
// Inertia válida (419 = token CSRF caducado, 401 = sin autenticar), en lugar de
// dejar que la petición falle en silencio o muestre un modal críptico, avisamos
// al usuario y lo llevamos a iniciar sesión. Lo que estuviera escribiendo queda
// a salvo como borrador local (ver ConfigMessages.vue y otros formularios).
router.on('invalid', (event) => {
    const status = event.detail?.response?.status;
    if (status === 419 || status === 401) {
        event.preventDefault();
        toast.error(
            'Tu sesión expiró. Vuelve a iniciar sesión — lo que escribiste quedó guardado como borrador en este navegador.',
            { autoClose: 8000 },
        );
        window.setTimeout(() => { window.location.href = '/login'; }, 3000);
    }
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(Vue3Toastify, { autoClose: 4000 })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
