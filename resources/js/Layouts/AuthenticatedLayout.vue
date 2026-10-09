<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';
import { usePrimaryColor } from '@/composables/usePrimaryColor';

usePrimaryColor();
const showingNavigationDropdown = ref(false);
const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const logoUrl = computed(() => page.props.settings?.company_logo_url || '/met-logo.png');
const faviconUrl = computed(() => page.props.settings?.favicon_url || '/favicon.png');
</script>

<template>
    <div>
        <div class="flex min-h-screen flex-col bg-[#f5f8fa] text-[14px]">
            <nav
                class="border-b border-[#e3e8ee] bg-white shadow-sm"
            >
                <!-- Primary Navigation Menu -->
                <div class="w-full px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center gap-2">
                                <Link :href="route('dashboard')" class="flex items-center gap-2">
                                    <img :src="logoUrl" alt="Logo" class="h-9 w-auto" />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex"
                            >
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    Dashboard
                                </NavLink>
                                <NavLink
                                    :href="route('agents.index')"
                                    :active="route().current('agents.*')"
                                >
                                    Empresas
                                </NavLink>
                                <NavLink
                                    v-if="$page.props.auth.canAccessSettings"
                                    :href="route('settings.index')"
                                    :active="route().current('settings.*')"
                                >
                                    Configuración
                                </NavLink>
                                <NavLink
                                    v-if="$page.props.auth.canAccessSettings"
                                    :href="route('users.index')"
                                    :active="route().current('users.*')"
                                >
                                    Usuarios
                                </NavLink>
                                <NavLink
                                    :href="route('library.index')"
                                    :active="route().current('library.*')"
                                >
                                    Library
                                </NavLink>
                                <NavLink
                                    :href="route('docs.index')"
                                    :active="route().current('docs.*')"
                                >
                                    Cómo usar
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-[#444444] transition duration-150 ease-in-out hover:text-[#133c75] focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            v-if="$page.props.auth.canAccessSettings"
                                            :href="route('settings.index')"
                                        >
                                            Configuración
                                        </DropdownLink>
                                        <DropdownLink
                                            v-if="$page.props.auth.canAccessSettings"
                                            :href="route('users.index')"
                                        >
                                            Usuarios
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('agents.index')"
                            :active="route().current('agents.*')"
                        >
                            Empresas
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="$page.props.auth.canAccessSettings"
                            :href="route('settings.index')"
                            :active="route().current('settings.*')"
                        >
                            Configuración
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="$page.props.auth.canAccessSettings"
                            :href="route('users.index')"
                            :active="route().current('users.*')"
                        >
                            Usuarios
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('library.index')"
                            :active="route().current('library.*')"
                        >
                            Library
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('docs.index')"
                            :active="route().current('docs.*')"
                        >
                            Cómo usar
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-gray-200 pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-gray-800"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Flash Messages -->
            <div
                v-if="flashSuccess || flashError"
                class="w-full px-4 sm:px-6 lg:px-8"
            >
                <div
                    v-if="flashSuccess"
                    class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800"
                >
                    {{ flashSuccess }}
                </div>
                <div
                    v-else-if="flashError"
                    class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
                >
                    {{ flashError }}
                </div>
            </div>

            <!-- Page Heading -->
            <header
                class="border-b border-[#e3e8ee] bg-white"
                v-if="$slots.header"
            >
                <div class="w-full px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1">
                <slot />
            </main>

            <!-- Footer -->
            <footer class="mt-auto border-t border-[#e3e8ee] bg-white py-4">
                <div class="flex w-full items-center justify-center gap-2 px-4 text-center text-sm text-[#444444] sm:px-6 lg:px-8">
                    <img src="/ainoa.png" alt="ainoa" class="h-6 w-6" />
                    <a href="https://ainoa.app" target="_blank" rel="noopener noreferrer" class="transition hover:text-[var(--color-primary)]">
                        By ainoa
                    </a>
                </div>
            </footer>
        </div>
    </div>
</template>
