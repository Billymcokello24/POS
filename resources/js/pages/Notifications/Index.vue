<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { Bell, Check, CheckCheck, Trash2, Filter } from 'lucide-vue-next'
import { ref, computed } from 'vue'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import AppLayout from '@/layouts/app/AppSidebarLayout.vue'

interface Notification {
    id: string
    type: string
    data: {
        type: string
        title: string
        message: string
        icon?: string
        [key: string]: any
    }
    read_at: string | null
    created_at: string
}

interface Props {
    notifications: {
        data: Notification[]
        links: any[]
        meta: any
    }
    unreadCount: number
}

const props = defineProps<Props>()

const filter = ref<'all' | 'unread' | 'read'>('all')

const filteredNotifications = computed(() => {
    if (filter.value === 'all') return props.notifications.data
    if (filter.value === 'unread') return props.notifications.data.filter(n => !n.read_at)
    if (filter.value === 'read') return props.notifications.data.filter(n => n.read_at)
    return props.notifications.data
})

function markAsRead(id: string) {
    router.post(`/notifications/${id}/mark-as-read`, {}, {
        preserveScroll: true,
    })
}

function markAllAsRead() {
    router.post('/notifications/mark-all-as-read', {}, {
        preserveScroll: true,
    })
}

function formatDate(date: string) {
    return new Date(date).toLocaleString()
}

function getNotificationColor(type: string) {
    const typeMap: Record<string, string> = {
        'subscription.created': 'bg-blue-100 text-blue-800',
        'subscription.activated': 'bg-green-100 text-green-800',
        'subscription.expiring': 'bg-yellow-100 text-yellow-800',
        'subscription.upgraded': 'bg-purple-100 text-purple-800',
        'subscription.downgraded': 'bg-orange-100 text-orange-800',
        'impersonation.started': 'bg-red-100 text-red-800',
        'product.created': 'bg-indigo-100 text-indigo-800',
        'category.created': 'bg-pink-100 text-pink-800',
        'business.auto_suspended': 'bg-red-100 text-red-800',
    }
    return typeMap[type] || 'bg-gray-100 text-gray-800'
}
</script>

<template>
    <Head title="Notifications" />

    <AppLayout>
        <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 py-4 sm:py-8 px-3 sm:px-6">
            <div class="max-w-4xl mx-auto space-y-4 sm:space-y-6">
                <!-- Header - Mobile Optimized -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
                    <div class="min-w-0 flex-1">
                        <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 flex items-center gap-2 sm:gap-3">
                            <Bell class="h-6 w-6 sm:h-8 sm:w-8 text-blue-600 flex-shrink-0" />
                            <span class="truncate">Notifications</span>
                        </h1>
                        <p class="text-slate-600 mt-1 text-xs sm:text-sm truncate">Stay updated with your business activities</p>
                    </div>
                    <Button
                        v-if="unreadCount > 0"
                        @click="markAllAsRead"
                        variant="outline"
                        class="gap-1 sm:gap-2 h-9 sm:h-10 px-3 sm:px-4 text-xs sm:text-sm flex-shrink-0"
                    >
                        <CheckCheck class="h-3 w-3 sm:h-4 sm:w-4" />
                        <span class="hidden xs:inline">Mark All as Read</span>
                        <span class="xs:hidden">Mark All</span>
                    </Button>
                </div>

                <!-- Stats - Mobile Optimized -->
                <div class="grid grid-cols-3 gap-2 sm:gap-4">
                    <Card>
                        <CardContent class="pt-3 sm:pt-6 p-3 sm:p-6">
                            <div class="text-center">
                                <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-blue-600">{{ notifications?.meta?.total || notifications.data.length }}</div>
                                <div class="text-xs sm:text-sm text-slate-600 mt-1">Total</div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-3 sm:pt-6 p-3 sm:p-6">
                            <div class="text-center">
                                <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-orange-600">{{ unreadCount }}</div>
                                <div class="text-xs sm:text-sm text-slate-600 mt-1">Unread</div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-3 sm:pt-6 p-3 sm:p-6">
                            <div class="text-center">
                                <div class="text-xl sm:text-2xl lg:text-3xl font-bold text-green-600">{{ (notifications?.meta?.total || notifications.data.length) - unreadCount }}</div>
                                <div class="text-xs sm:text-sm text-slate-600 mt-1">Read</div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Filters - Mobile Optimized -->
                <Tabs v-model="filter" default-value="all" class="w-full">
                    <TabsList class="grid w-full grid-cols-3">
                        <TabsTrigger value="all" class="text-xs sm:text-sm">All</TabsTrigger>
                        <TabsTrigger value="unread" class="text-xs sm:text-sm">Unread</TabsTrigger>
                        <TabsTrigger value="read" class="text-xs sm:text-sm">Read</TabsTrigger>
                    </TabsList>
                </Tabs>

                <!-- Notifications List - Mobile Optimized -->
                <div class="space-y-2 sm:space-y-3">
                    <div
                        v-for="notification in filteredNotifications"
                        :key="notification.id"
                        :class="[
                            'bg-white rounded-lg sm:rounded-xl shadow-sm border transition-all hover:shadow-md',
                            !notification.read_at ? 'border-l-4 border-l-blue-600' : 'border-slate-200'
                        ]"
                    >
                        <div class="p-3 sm:p-5 flex items-start gap-2 sm:gap-4">
                            <!-- Icon -->
                            <div class="text-xl sm:text-3xl flex-shrink-0">
                                {{ notification.data.icon || '📬' }}
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2 sm:gap-3">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold text-slate-900 text-sm sm:text-base truncate">{{ notification.data.title }}</h3>
                                        <p class="text-slate-600 text-xs sm:text-sm mt-1 line-clamp-2">{{ notification.data.message }}</p>
                                        <div class="flex flex-col xs:flex-row xs:items-center gap-2 xs:gap-3 mt-2 sm:mt-3">
                                            <Badge :class="getNotificationColor(notification.data.type)" class="text-[10px] sm:text-xs w-fit">
                                                {{ notification.data.type }}
                                            </Badge>
                                            <span class="text-[10px] sm:text-xs text-slate-500">{{ formatDate(notification.created_at) }}</span>
                                        </div>
                                    </div>
                                    <Button
                                        v-if="!notification.read_at"
                                        @click="markAsRead(notification.id)"
                                        variant="ghost"
                                        size="sm"
                                        class="gap-1 sm:gap-2 h-8 w-8 sm:h-9 sm:w-9 p-0 flex-shrink-0"
                                    >
                                        <Check class="h-3 w-3 sm:h-4 sm:w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State - Mobile Optimized -->
                    <div v-if="filteredNotifications.length === 0" class="text-center py-12 sm:py-16">
                        <Bell class="h-12 w-12 sm:h-16 sm:w-16 text-slate-300 mx-auto mb-3 sm:mb-4" />
                        <h3 class="text-base sm:text-lg font-semibold text-slate-900">No notifications</h3>
                        <p class="text-slate-600 mt-1 text-sm">You're all caught up!</p>
                    </div>
                </div>

                <!-- Pagination - Mobile Optimized -->
                <div v-if="notifications.links.length > 3" class="flex justify-center gap-1 sm:gap-2 flex-wrap">
                    <Button
                        v-for="link in notifications.links"
                        :key="link.label"
                        @click="router.visit(link.url)"
                        :disabled="!link.url"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        class="h-8 min-w-[32px] sm:h-9 sm:min-w-[36px] px-2 sm:px-3 text-xs"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

