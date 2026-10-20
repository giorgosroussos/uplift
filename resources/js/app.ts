import { createInertiaApp } from '@inertiajs/vue3';
import { i18nVue } from 'laravel-vue-i18n';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { initializeEcho } from '@/lib/echo';
import { initializeFlashToast } from '@/lib/flashToast';
import { resolveLanguage } from '@/lib/i18n';
import { initializeOrganizationTheme } from '@/lib/organizationTheme';

initializeEcho();

// The organisation's accent colour as primary colour (DATA-011); registered
// before the app starts so the first page is themed too.
initializeOrganizationTheme();

const appName = import.meta.env.VITE_APP_NAME || 'Uplift';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.use(i18nVue, {
            lang: document.documentElement.lang || 'en',
            fallbackLang: 'en',
            resolve: resolveLanguage,
        });
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
