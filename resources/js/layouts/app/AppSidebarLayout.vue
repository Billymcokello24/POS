<script setup lang="ts">
import { usePage, Link, router } from '@inertiajs/vue3';
import { ShieldAlert, LogOut } from 'lucide-vue-next';

import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import SupportWidget from '@/components/SupportWidget.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

function doStopImpersonation() {
    router.post('/admin/businesses/stop-impersonating');
}
</script>

<template>
    <AppShell variant="sidebar">
        <FlashMessage />
        <!-- Impersonation Banner -->
        <div v-if="$page.props.auth && $page.props.auth.is_impersonating"
             class="fixed top-0 left-0 right-0 z-[100] bg-slate-900 border-b border-white/10 backdrop-blur-md py-3 px-6 shadow-2xl flex items-center justify-between animate-in slide-in-from-top duration-500 overflow-hidden">
            <!-- Animated scanline effect for "Secure Mode" feel -->
            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-indigo-500/10 to-transparent -translate-x-full animate-[shimmer_3s_infinite]"></div>
            
            <div class="flex items-center gap-5 relative z-10">
                <div class="flex items-center gap-2 px-3 py-1 bg-indigo-500 rounded-lg shadow-inner">
                    <ShieldAlert class="h-4 w-4 text-white animate-pulse" />
                    <span class="text-[10px] font-black uppercase tracking-[0.2em] text-white">Administrative Proxy Active</span>
                </div>
                
                <div class="h-4 w-[1px] bg-white/20"></div>
                
                <div class="flex items-center gap-3">
                    <div class="flex flex-col">
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest leading-none mb-1">Target Account</span>
                        <span class="text-sm font-black text-white leading-none tracking-tight">{{ $page.props.auth.user.name }}</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4 relative z-10">
                <div class="hidden lg:flex flex-col items-end px-4 border-r border-white/10">
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest leading-none mb-1">Session Integrity</span>
                    <span class="text-[10px] font-black text-emerald-400 leading-none">HIGHLY SECURE</span>
                </div>

                <form @submit.prevent="doStopImpersonation">
                    <button type="submit" class="h-10 px-6 bg-white text-slate-900 font-black text-xs uppercase tracking-widest rounded-xl hover:bg-slate-100 hover:scale-[1.02] transition-all active:scale-95 shadow-lg flex items-center gap-2 group">
                        <LogOut class="h-4 w-4 text-indigo-600 transition-transform group-hover:-translate-x-1" />
                        End Session & Return
                    </button>
                </form>
            </div>
        </div>

        <AppSidebar />
        <AppContent variant="sidebar" :class="'overflow-x-hidden ' + ($page.props.auth?.is_impersonating ? 'pt-10' : '')">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>

        <!-- Support widget for business users -->
        <SupportWidget v-if="$page.props.auth?.user && !$page.props.auth.user.is_super_admin" />
    </AppShell>
</template>
