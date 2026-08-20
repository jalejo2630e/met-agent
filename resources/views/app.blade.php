<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
        <style>
            :root {
                --color-primary: #a3e635;
                --color-primary-hover: color-mix(in srgb, var(--color-primary) 85%, black);
                --color-primary-active: color-mix(in srgb, var(--color-primary) 70%, black);
                --color-primary-light: color-mix(in srgb, var(--color-primary) 15%, white);
                --color-primary-foreground: #1a1a1a;
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @routes
        @vite(['resources/css/app.css', 'resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
