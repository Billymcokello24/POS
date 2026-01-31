/* eslint-disable import/order */
import '../css/app.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import axios from './axios';
import { initializeTheme } from './composables/useAppearance';
import { ensureSanctum, attachSanctumToAxios } from './lib/sanctum';
import { startBusinessSse } from './lib/sse';
import { startRealtimePolling } from './lib/realtime';
import './echo'; // Initialize Laravel Echo for WebSocket support

// Defensive wrapper: intercept 'keydown' listeners and wrap them in a try/catch so
// a single faulty handler (e.g. reading .length on undefined) doesn't throw an
// uncaught exception and break other code. This logs the error and allows the
// application to continue running. Useful for debugging unknown third-party
// bundles that attach global keydown handlers.
; (function () {
    const proto: any = EventTarget && (EventTarget.prototype as any);
    if (!proto) return;

    const originalAdd = proto.addEventListener;

    proto.addEventListener = function (type: string, listener: any, options?: any) {
        if (type === 'keydown' && typeof listener === 'function') {
            const safe = function (this: any, ...args: any[]) {
                try {
                    return listener.apply(this, args);
                } catch (err) {
                    // Log with as much context as possible without exposing secrets
                    try {
                        const ev = args && args[0];
                        console.error('[SafeKeydown] handler error:', err, {
                            eventType: ev?.type,
                            key: ev?.key,
                            listenerName: listener && listener.name,
                        });
                    } catch {
                        // swallow
                    }
                    // swallow the error to avoid uncaught exceptions
                    return undefined;
                }
            };
            // Mark wrapper so double-wrapping won't occur
            (safe as any).__isSafeKeydown = true;
            return originalAdd.call(this, type, safe, options);
        }
        return originalAdd.call(this, type, listener, options);
    };
})();

// Wrap direct assignments to document.onkeydown (some bundles use document.onkeydown = handler)
(function () {
    try {
        const doc: any = document;
        let current: any = doc.onkeydown;

        Object.defineProperty(doc, 'onkeydown', {
            configurable: true,
            enumerable: true,
            get() {
                return current;
            },
            set(fn: any) {
                if (typeof fn === 'function') {
                    // Avoid double-wrapping
                    if ((fn as any).__isSafeOnKeydown) {
                        current = fn;
                        return;
                    }
                    const safe = function (this: any, ev: any) {
                        try {
                            return fn.call(this, ev);
                        } catch (err) {
                            try { console.error('[SafeOnKeydown] handler error:', err, { key: ev?.key, type: ev?.type }); } catch { }
                            return undefined;
                        }
                    } as any;
                    safe.__isSafeOnKeydown = true;
                    current = safe;
                } else {
                    current = fn;
                }
                // keep window.onkeydown synced for environments that check it there
                try { (window as any).onkeydown = current; } catch { }
            }
        });

        // If there's already an assigned handler, wrap it now
        if (typeof current === 'function' && !(current as any).__isSafeOnKeydown) {
            const orig = current;
            (doc as any).onkeydown = function (ev: any) {
                try { return orig.call(this, ev); } catch (err) { try { console.error('[SafeOnKeydown] existing handler error:', err); } catch { } }
            };
        }
    } catch {
        // ignore safety wrapper failures
    }
})();

// Initialize Sanctum (fetch csrf cookie) and attach to axios defaults
; (async () => {
    try {
        // Import the helpers that ensure the XSRF cookie is available and attach axios interceptors
        await ensureSanctum()
        attachSanctumToAxios(axios)
    } catch {
        console.warn('Sanctum initialization failed')
    }
})();

// Axios defaults (baseURL, credentials, CSRF handling, interceptors) are configured in resources/js/axios.ts

// Ensure axios uses the same origin/scheme as the page (avoids mixed-content http requests when served via https/ngrok)
try {
    if (typeof window !== 'undefined' && window.location && window.location.origin) {
        axios.defaults.baseURL = window.location.origin;
    }
} catch {
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
        // Start SSE for authenticated users (Inertia sets page props on root)
        try {
            const pageProps = (props as any).initialPage?.props ?? (props as any).page?.props ?? (props as any).props ?? null;
            // If user is present in page props, assume authenticated and start SSE
            const sseEnabled = import.meta.env.VITE_ENABLE_SSE === 'true' || import.meta.env.VITE_ENABLE_SSE === true;
            const authed = pageProps && (pageProps.user || pageProps.auth?.user || pageProps.authUser);
            if (authed) {
                if (sseEnabled) {
                    startBusinessSse();
                } else {
                    // Start polling fallback when SSE disabled
                    startRealtimePolling(5000);
                }
            }
        } catch {
            // Start it anyway as a fallback
            try {
                // Fallback: start polling if SSE flag not enabled
                const sseEnabled = import.meta.env.VITE_ENABLE_SSE === 'true' || import.meta.env.VITE_ENABLE_SSE === true;
                if (sseEnabled) {
                    startBusinessSse();
                } else {
                    startRealtimePolling(5000);
                }
            } catch { }
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
