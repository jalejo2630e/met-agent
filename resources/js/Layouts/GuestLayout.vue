<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePrimaryColor } from '@/composables/usePrimaryColor';

usePrimaryColor();

const page = usePage();
const faviconUrl = computed(() => page.props.settings?.favicon_url || '/favicon.png');
const logoUrl = computed(() => page.props.settings?.company_logo_url || '/met-logo.png');
const companyName = computed(() => page.props.settings?.company_name);
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-[#f5f8fa] pt-6 sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/" class="flex justify-center">
                <img :src="logoUrl" :alt="companyName || 'Logo'" :title="companyName || 'Logo'" class="h-14 w-auto" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden rounded-lg border border-[#e3e8ee] bg-white px-6 py-6 sm:max-w-md shadow-sm"
        >
            <slot />
        </div>

        <footer class="mt-auto w-full border-t border-[#e3e8ee] bg-white py-4">
            <div class="flex items-center justify-center gap-2 text-center text-sm text-[#444444]">
                <img src="/ainoa.png" alt="ainoa" class="h-6 w-6" />
                <a href="https://ainoa.app" target="_blank" rel="noopener noreferrer" class="transition hover:text-[var(--color-primary)]">
                    By ainoa
                </a>
            </div>
        </footer>
    </div>
</template>
