import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import {
    initializeNavigationState,
    SLOW_NAVIGATION_MS,
} from '@/composables/useNavigationState';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { initializePrefetchCache } from '@/lib/prefetchCache';
import { loadLanguage, pageLocale, trans } from '@/lib/i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Loaded before the first render so the page never flashes untranslated text.
await loadLanguage(pageLocale());

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.config.globalProperties.$t = trans;
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        // Inertia puts the colour into the bar's style as it is, so the variable follows light and dark mode.
        color: 'var(--primary)',
        delay: SLOW_NAVIGATION_MS,
        showSpinner: false,
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

initializeNavigationState();

initializePrefetchCache();
