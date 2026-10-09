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
                sans: ['Plus Jakarta Sans', 'system-ui', '-apple-system', ...defaultTheme.fontFamily.sans],
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
                    navy: '#133c75',
                    'navy-light': '#444444',
                    'navy-dark': '#0b2850',
                    gray: '#f5f8fa',
                    'gray-border': '#e3e8ee',
                },
            },
        },
    },

    plugins: [forms],
};
