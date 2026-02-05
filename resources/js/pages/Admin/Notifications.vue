<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { Bell, Check, CheckCheck } from 'lucide-vue-next'
import { ref, computed } from 'vue'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import AdminLayout from '@/layouts/AdminLayout.vue'

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
    let filtered = [...props.notifications.data]

    // Filter out notifications older than 24 hours
    const twentyFourHoursAgo = new Date(Date.now() - 24 * 60 * 60 * 1000)
    filtered = filtered.filter(n => {
        if (!n.created_at) return false
        return new Date(n.created_at) > twentyFourHoursAgo
    })

    // Apply user-selected filters
    if (filter.value === 'unread') {
        filtered = filtered.filter(n => !n.read_at)
    } else if (filter.value === 'read') {
        filtered = filtered.filter(n => n.read_at)
    }

    // Sort by newest first and limit to 10
    return filtered
        .sort((a, b) => {
            const dateA = new Date(a.created_at || 0).getTime()
            const dateB = new Date(b.created_at || 0).getTime()
            return dateB - dateA
        })
        .slice(0, 10)
})

function markAsRead(id: string) {
    router.post(`/admin/notifications/${id}/mark-as-read`, {}, {
        preserveScroll: true,
    })
}

function markAllAsRead() {
    router.post('/admin/notifications/mark-all-as-read', {}, {
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
        'business.auto_suspended': 'bg-red-100 text-red-800',
        'support.ticket.created': 'bg-indigo-100 text-indigo-800',
    }
    return typeMap[type] || 'bg-gray-100 text-gray-800'
}
</script>

<template>
    <Head title="Admin Notifications" />

    <AdminLayout>
        <div class="min-h-screen bg-slate-50 py-8 px-6">
            <div class="max-w-5xl mx-auto space-y-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-900 flex items-center gap-3">
                            <Bell class="h-8 w-8 text-blue-600" />
                            Admin Notifications
                        </h1>
                        <p class="text-slate-600 mt-1">Platform-wide notifications and alerts</p>
                    </div>
                    <Button
                        v-if="unreadCount > 0"
                        @click="markAllAsRead"
                        variant="outline"
                        class="gap-2"
                    >
                        <CheckCheck class="h-4 w-4" />
                        Mark All as Read
                    </Button>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-3 gap-4">
                    <Card>
                        <CardContent class="pt-6">
                            <div class="text-center">
                                <div class="text-3xl font-bold text-blue-600">{{ filteredNotifications.length }}</div>
                                <div class="text-sm text-slate-600 mt-1">Last 24hrs (Max 10)</div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-6">
                            <div class="text-center">
                                <div class="text-3xl font-bold text-orange-600">{{ unreadCount }}</div>
                                <div class="text-sm text-slate-600 mt-1">Unread</div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-6">
                            <div class="text-center">
                                <div class="text-3xl font-bold text-green-600">{{ filteredNotifications.filter(n => n.read_at).length }}</div>
                                <div class="text-sm text-slate-600 mt-1">Read</div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Filters -->
                <Tabs v-model="filter" default-value="all" class="w-full">
                    <TabsList class="grid w-full grid-cols-3">
                        <TabsTrigger value="all">All</TabsTrigger>
                        <TabsTrigger value="unread">Unread</TabsTrigger>
                        <TabsTrigger value="read">Read</TabsTrigger>
                    </TabsList>
                </Tabs>

                <!-- Notifications List -->
                <div class="space-y-3">
                    <div
                        v-for="notification in filteredNotifications"
                        :key="notification.id"
                        :class="[
                            'bg-white rounded-xl shadow-sm border transition-all hover:shadow-md',
                            !notification.read_at ? 'border-l-4 border-l-blue-600' : 'border-slate-200'
                        ]"
                    >
                        <div class="p-5 flex items-start gap-4">
                            <!-- Icon -->
                            <div class="text-3xl flex-shrink-0">
                                {{ notification.data.icon || '📬' }}
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-slate-900">{{ notification.data.title }}</h3>
                                        <p class="text-slate-600 text-sm mt-1">{{ notification.data.message }}</p>
                                        <div class="flex items-center gap-3 mt-3">
                                            <Badge :class="getNotificationColor(notification.data.type)">
                                                {{ notification.data.type }}
                                            </Badge>
                                            <span class="text-xs text-slate-500">{{ formatDate(notification.created_at) }}</span>
                                        </div>
                                    </div>
                                    <Button
                                        v-if="!notification.read_at"
                                        @click="markAsRead(notification.id)"
                                        variant="ghost"
                                        size="sm"
                                        class="gap-2"
                                    >
                                        <Check class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="filteredNotifications.length === 0" class="text-center py-16">
                        <Bell class="h-16 w-16 text-slate-300 mx-auto mb-4" />
                        <h3 class="text-lg font-semibold text-slate-900">No notifications</h3>
                        <p class="text-slate-600 mt-1">You're all caught up!</p>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="notifications.links.length > 3" class="flex justify-center gap-2">
                    <Button
                        v-for="link in notifications.links"
                        :key="link.label"
                        @click="router.visit(link.url)"
                        :disabled="!link.url"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

