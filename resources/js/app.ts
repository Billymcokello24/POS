import '../css/app.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import axios from 'axios';
import { initializeTheme } from './composables/useAppearance';

// Configure axios globally for CSRF and cookies
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;

// Ensure axios uses the same origin/scheme as the page (avoids mixed-content http requests when served via https/ngrok)
try {
    if (typeof window !== 'undefined' && window.location && window.location.origin) {
        axios.defaults.baseURL = window.location.origin;
    }
} catch (e) {
    // ignore in non-browser environments
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
