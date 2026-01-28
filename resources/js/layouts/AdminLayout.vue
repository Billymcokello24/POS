<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import {
    LayoutDashboard,
    Building2,
    CreditCard,
    Wrench,
    ShieldCheck,
    MessageSquare,
    Settings,
    LogOut,
    Menu,
    X,
    Monitor,
    Activity,
    Sparkles
} from 'lucide-vue-next'
import { ref } from 'vue'

import LogoutButton from '@/components/LogoutButton.vue'

const isOpen = ref(false)
const page = usePage()

const navigation = [
    { name: 'Platform Health', href: '/admin/dashboard', icon: LayoutDashboard },
    { name: 'Business Management', href: '/admin/businesses', icon: Building2 },
    { name: 'Revenue & Subscriptions', href: '/admin/subscriptions', icon: CreditCard },
    { name: 'Feature Management', href: '/admin/features', icon: Wrench },
    { name: 'Global User Roles', href: '/admin/roles', icon: ShieldCheck },
    { name: 'Platform Command (CMS)', href: '/admin/cms', icon: Monitor },
    { name: 'Plan Management', href: '/admin/plans', icon: Sparkles },
    { name: 'System Audit Trails', href: '/admin/audit-logs', icon: Activity },
]

const isActive = (href: string) => page.url.startsWith(href)
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex">
        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-white transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0',
                isOpen ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <div class="flex flex-col h-full">
                <!-- Logo -->
                <div class="h-16 flex items-center px-6 border-b border-slate-800">
                    <div class="w-8 h-8 bg-red-600 rounded flex items-center justify-center font-bold mr-3">SA</div>
                    <span class="text-lg font-bold tracking-tight">SUPER <span class="text-blue-500">ADMIN</span></span>
                </div>

                <!-- Nav -->
                <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                    <div v-for="item in navigation" :key="item.name">
                         <Link
                            :href="item.href"
                            :class="[
                                'flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors group',
                                isActive(item.href) ? 'bg-blue-600 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800'
                            ]"
                        >
                            <component :is="item.icon" class="mr-3 h-5 w-5 shrink-0" />
                            <span class="flex-1">{{ item.name }}</span>
                        </Link>
                    </div>
                </nav>

                <div class="p-4 border-t border-slate-800">
                    <LogoutButton />
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Header -->
            <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-8">
                <button @click="isOpen = !isOpen" class="lg:hidden text-slate-600">
                    <Menu v-if="!isOpen" class="h-6 w-6" />
                    <X v-else class="h-6 w-6" />
                </button>

                <div class="flex-1 px-4 flex justify-between">
                    <h2 class="text-lg font-semibold text-slate-800 self-center">
                        {{ $page.props.title || 'Platform Dashboard' }}
                    </h2>

                    <div class="flex items-center gap-4">
                        <div class="text-right hidden sm:block">
                            <div class="text-sm font-bold text-slate-900">Billy Admin</div>
                            <div class="text-xs text-slate-500 font-medium">Head of Platform</div>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold border-2 border-blue-200">
                            BA
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Body -->
            <main class="flex-1 overflow-y-auto p-4 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
