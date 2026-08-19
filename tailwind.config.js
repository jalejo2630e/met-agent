import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'system-ui', '-apple-system', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Color principal: viene de Configuración (settings.primary_color), aplicado como --color-primary
                primary: {
                    DEFAULT: 'var(--color-primary)',
                    hover: 'var(--color-primary-hover)',
                    light: 'var(--color-primary-light)',
                    foreground: 'var(--color-primary-foreground)',
                },
                hubspot: {
                    navy: '#33475b',
                    'navy-light': '#425b76',
                    'navy-dark': '#1e2d3b',
                    gray: '#f5f8fa',
                    'gray-border': '#e3e8ee',
                },
            },
        },
    },

    plugins: [forms],
};
