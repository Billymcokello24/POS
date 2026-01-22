<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import {
    LayoutGrid,
    ShoppingCart,
    Package,
    BarChart3,
    Box,
    Users,
    Settings,
    Store,
    TrendingUp,
    AlertCircle,
    Sparkles,
    ChevronRight
} from 'lucide-vue-next'

import NavUser from '@/components/NavUser.vue'
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarGroupContent,
} from '@/components/ui/sidebar'
import { type NavItem } from '@/types'

const page = usePage()

const isActive = (href: any) => {
    const url = typeof href === 'string' ? href : href?.url || ''
    return page.url.startsWith(url)
}

// Main Navigation Items
const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Point of Sale',
        href: '/sales/create',
        icon: ShoppingCart,
    },
]

// Sales & Orders
const salesNavItems: NavItem[] = [
    {
        title: 'Sales History',
        href: '/sales',
        icon: TrendingUp,
    },
]

// Inventory Management
const inventoryNavItems: NavItem[] = [
    {
        title: 'Products',
        href: '/products',
        icon: Package,
    },
    {
        title: 'Categories',
        href: '/categories',
        icon: Box,
    },
    {
        title: 'Inventory',
        href: '/inventory',
        icon: AlertCircle,
    },
]

// Reports & Analytics
const reportsNavItems: NavItem[] = [
    {
        title: 'All Reports',
        href: '/reports',
        icon: BarChart3,
    },
]

// Settings
const settingsNavItems: NavItem[] = [
    {
        title: 'Business Settings',
        href: '/business/settings',
        icon: Store,
    },
    {
        title: 'Users',
        href: '/users',
        icon: Users,
    },
    {
        title: 'System Settings',
        href: '/settings',
        icon: Settings,
    },
]
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-r-0">
        <!-- Gradient Header -->
        <SidebarHeader class="bg-gradient-to-br from-purple-600 via-pink-600 to-orange-500 p-4">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="hover:bg-white/20 data-[state=open]:bg-white/20">
                        <Link href="/dashboard" class="flex items-center gap-3">
                            <div class="flex aspect-square size-10 items-center justify-center rounded-xl bg-white/20 backdrop-blur text-white">
                                <Sparkles class="size-6" />
                            </div>
                            <div class="grid flex-1 text-left text-sm leading-tight">
                                <span class="truncate font-bold text-white text-lg">POS System</span>
                                <span class="truncate text-xs text-white/80">Point of Sale</span>
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="bg-gradient-to-b from-slate-50 to-white">
            <!-- Main Navigation -->
            <SidebarGroup>
                <SidebarGroupLabel class="text-xs font-semibold text-slate-600 uppercase tracking-wider px-4 py-2">
                    Main Menu
                </SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in mainNavItems" :key="item.title">
                            <SidebarMenuButton
                                as-child
                                :class="[
                                    'group relative my-1 mx-2 rounded-xl transition-all',
                                    isActive(item.href)
                                        ? 'bg-gradient-to-r from-purple-600 to-pink-600 text-white shadow-lg shadow-purple-500/50'
                                        : 'hover:bg-slate-100'
                                ]"
                            >
                                <Link :href="item.href" class="flex items-center gap-3 px-3 py-2.5">
                                    <component
                                        :is="item.icon"
                                        :class="[
                                            'size-5 transition-transform group-hover:scale-110',
                                            isActive(item.href) ? 'text-white' : 'text-slate-600'
                                        ]"
                                    />
                                    <span
                                        :class="[
                                            'font-medium',
                                            isActive(item.href) ? 'text-white' : 'text-slate-700'
                                        ]"
                                    >
                                        {{ item.title }}
                                    </span>
                                    <ChevronRight
                                        v-if="isActive(item.href)"
                                        class="ml-auto size-4 text-white"
                                    />
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <!-- Sales Section -->
            <SidebarGroup>
                <SidebarGroupLabel class="text-xs font-semibold text-slate-600 uppercase tracking-wider px-4 py-2">
                    <TrendingUp class="inline size-3 mr-2" />
                    Sales & Orders
                </SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in salesNavItems" :key="item.title">
                            <SidebarMenuButton
                                as-child
                                :class="[
                                    'group relative my-1 mx-2 rounded-xl transition-all',
                                    isActive(item.href)
                                        ? 'bg-gradient-to-r from-blue-600 to-cyan-600 text-white shadow-lg shadow-blue-500/50'
                                        : 'hover:bg-slate-100'
                                ]"
                            >
                                <Link :href="item.href" class="flex items-center gap-3 px-3 py-2.5">
                                    <component
                                        :is="item.icon"
                                        :class="[
                                            'size-5 transition-transform group-hover:scale-110',
                                            isActive(item.href) ? 'text-white' : 'text-slate-600'
                                        ]"
                                    />
                                    <span
                                        :class="[
                                            'font-medium',
                                            isActive(item.href) ? 'text-white' : 'text-slate-700'
                                        ]"
                                    >
                                        {{ item.title }}
                                    </span>
                                    <ChevronRight
                                        v-if="isActive(item.href)"
                                        class="ml-auto size-4 text-white"
                                    />
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <!-- Inventory Section -->
            <SidebarGroup>
                <SidebarGroupLabel class="text-xs font-semibold text-slate-600 uppercase tracking-wider px-4 py-2">
                    <Package class="inline size-3 mr-2" />
                    Inventory
                </SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in inventoryNavItems" :key="item.title">
                            <SidebarMenuButton
                                as-child
                                :class="[
                                    'group relative my-1 mx-2 rounded-xl transition-all',
                                    isActive(item.href)
                                        ? 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-lg shadow-emerald-500/50'
                                        : 'hover:bg-slate-100'
                                ]"
                            >
                                <Link :href="item.href" class="flex items-center gap-3 px-3 py-2.5">
                                    <component
                                        :is="item.icon"
                                        :class="[
                                            'size-5 transition-transform group-hover:scale-110',
                                            isActive(item.href) ? 'text-white' : 'text-slate-600'
                                        ]"
                                    />
                                    <span
                                        :class="[
                                            'font-medium',
                                            isActive(item.href) ? 'text-white' : 'text-slate-700'
                                        ]"
                                    >
                                        {{ item.title }}
                                    </span>
                                    <ChevronRight
                                        v-if="isActive(item.href)"
                                        class="ml-auto size-4 text-white"
                                    />
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <!-- Reports Section -->
            <SidebarGroup>
                <SidebarGroupLabel class="text-xs font-semibold text-slate-600 uppercase tracking-wider px-4 py-2">
                    <BarChart3 class="inline size-3 mr-2" />
                    Analytics
                </SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in reportsNavItems" :key="item.title">
                            <SidebarMenuButton
                                as-child
                                :class="[
                                    'group relative my-1 mx-2 rounded-xl transition-all',
                                    isActive(item.href)
                                        ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-lg shadow-indigo-500/50'
                                        : 'hover:bg-slate-100'
                                ]"
                            >
                                <Link :href="item.href" class="flex items-center gap-3 px-3 py-2.5">
                                    <component
                                        :is="item.icon"
                                        :class="[
                                            'size-5 transition-transform group-hover:scale-110',
                                            isActive(item.href) ? 'text-white' : 'text-slate-600'
                                        ]"
                                    />
                                    <span
                                        :class="[
                                            'font-medium',
                                            isActive(item.href) ? 'text-white' : 'text-slate-700'
                                        ]"
                                    >
                                        {{ item.title }}
                                    </span>
                                    <ChevronRight
                                        v-if="isActive(item.href)"
                                        class="ml-auto size-4 text-white"
                                    />
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <!-- Settings Section -->
            <SidebarGroup>
                <SidebarGroupLabel class="text-xs font-semibold text-slate-600 uppercase tracking-wider px-4 py-2">
                    <Settings class="inline size-3 mr-2" />
                    Configuration
                </SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in settingsNavItems" :key="item.title">
                            <SidebarMenuButton
                                as-child
                                :class="[
                                    'group relative my-1 mx-2 rounded-xl transition-all',
                                    isActive(item.href)
                                        ? 'bg-gradient-to-r from-slate-700 to-slate-900 text-white shadow-lg shadow-slate-500/50'
                                        : 'hover:bg-slate-100'
                                ]"
                            >
                                <Link :href="item.href" class="flex items-center gap-3 px-3 py-2.5">
                                    <component
                                        :is="item.icon"
                                        :class="[
                                            'size-5 transition-transform group-hover:scale-110',
                                            isActive(item.href) ? 'text-white' : 'text-slate-600'
                                        ]"
                                    />
                                    <span
                                        :class="[
                                            'font-medium',
                                            isActive(item.href) ? 'text-white' : 'text-slate-700'
                                        ]"
                                    >
                                        {{ item.title }}
                                    </span>
                                    <ChevronRight
                                        v-if="isActive(item.href)"
                                        class="ml-auto size-4 text-white"
                                    />
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>

        <!-- Gradient Footer -->
        <SidebarFooter class="bg-gradient-to-br from-slate-100 to-slate-200 border-t">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>

<style scoped>
/* Additional custom styles for extra polish */
.sidebar-menu-item:hover {
    transform: translateX(2px);
}
</style>

