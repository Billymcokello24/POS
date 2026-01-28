<script setup lang="ts">
import { usePage, Link } from '@inertiajs/vue3';
import { ShieldAlert, LogOut } from 'lucide-vue-next';

import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

function doStopImpersonation(e?: Event) {
    if (e && e.preventDefault) e.preventDefault();

    const serverForm = document.getElementById('stop-impersonating-form') as HTMLFormElement | null;
    if (serverForm) {
        serverForm.submit();
        return;
    }

    // fallback to programmatic submission (should rarely be used if server form exists)
    const tokenMeta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
    const token = tokenMeta?.getAttribute('content') || '';

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/admin/businesses/stop-impersonating';

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = '_token';
    input.value = token;
    form.appendChild(input);

    document.body.appendChild(form);
    form.submit();
}
</script>

<template>
    <AppShell variant="sidebar">
        <FlashMessage />
        <!-- Impersonation Banner -->
        <div v-if="$page.props.auth && $page.props.auth.is_impersonating"
             class="fixed top-0 left-0 right-0 z-[100] bg-indigo-600 text-white py-2 px-4 shadow-lg flex items-center justify-center gap-4 animate-in slide-in-from-top duration-300">
            <ShieldAlert class="h-4 w-4" />
            <span class="text-sm font-bold">MODE: IMPERSONATING TENANT ({{ $page.props.auth.user.name }})</span>
            <!-- Use a native form POST to ensure cookies + CSRF are sent by the browser -->
            <form @submit.prevent="doStopImpersonation" class="bg-white/20 hover:bg-white/30 text-[10px] font-bold uppercase tracking-widest px-3 py-1 rounded border border-white/30 transition-colors flex items-center gap-2">
                <button type="submit" class="flex items-center gap-2">
                    <LogOut class="h-3 w-3" />
                    Return to Admin
                </button>
            </form>
        </div>

        <AppSidebar />
        <AppContent variant="sidebar" :class="'overflow-x-hidden ' + ($page.props.auth?.is_impersonating ? 'pt-10' : '')">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
    </AppShell>
</template>
